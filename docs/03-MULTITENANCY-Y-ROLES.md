# 03 - Multitenancy y roles

Este es el documento más importante del proyecto. El riesgo número uno no es
que falle una pantalla: es que el Colegio Los Álamos vea los alumnos del
Colegio San Antonio, o que la madre de Martina vea la ficha del hijo del
vecino.

Se lee antes de tocar cualquier consulta.

---

# PARTE I — AISLAMIENTO ENTRE COLEGIOS

## 1. El mecanismo

**Patrón ya decidido y documentado en el portal de autoservicio
(`apps/portal-autoservicio/docs/03-MULTITENANCY.md`), y aquí reutilizado sin
cambios de enfoque:** columna `tenant_id` más **Row Level Security** de
PostgreSQL.

Definido en `api/database/migrations/2026_10_17_000018_activar_row_level_security.php`.

### 1.1 La función de contexto

```sql
CREATE FUNCTION public.app_tenant_id() RETURNS uuid ...
    valor := current_setting('app.tenant_id', true);
    IF valor IS NULL OR valor = '' THEN
        RAISE EXCEPTION ...
```

**Lanza excepción si no hay contexto.** Podría devolver `NULL` y filtrar en
silencio, pero se eligió que falle de forma ruidosa:

- Si devuelve `NULL`, la consulta devuelve cero filas sin aviso. Alguien lo
  "arregla" devolviendo `NULL` cuando hace falta (un reporte, un comando de
  consola) y la puerta queda abierta sin que nada lo delate.
- Si lanza excepción, el error aparece en el log **en el momento**, y se sabe
  exactamente qué operación se escapó del middleware.

Es el mismo criterio que un candado: importa que falle cerrado.

### 1.2 La política

Para cada una de las **21** tablas de negocio:

```sql
ALTER TABLE public.alumnos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alumnos FORCE ROW LEVEL SECURITY;

CREATE POLICY politica_aislamiento_alumnos ON public.alumnos
    FOR ALL
    TO PUBLIC
    USING (tenant_id = public.app_tenant_id())
    WITH CHECK (tenant_id = public.app_tenant_id());
```

Tres detalles que importan:

- **`USING`** filtra lo que se **lee**.
- **`WITH CHECK`** impide **escribir** una fila con un `tenant_id` distinto.
  Sin esto, un `INSERT` con el tenant equivocado dejaría una fila invisible
  que nadie podría ver ni borrar.
- **`FORCE ROW LEVEL SECURITY`** hace que la política aplique **también al
  dueño** de la tabla. Sin FORCE, el dueño la saltaría; y en desarrollo el
  rol de la aplicación suele ser el mismo que creó las tablas. Sin FORCE, el
  aislamiento sería decorativo justo en el entorno donde se prueba.

### 1.3 Cómo viaja el contexto

```php
DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $tenantId]);
```

El `true` final es lo más importante: **alcance de transición**.

Al confirmar o revertir la transacción, PostgreSQL descarta el valor solo. Con
un `SET` normal (alcance de sesión), el valor sobreviviría a la petición, y el
**worker de colas reutiliza la misma conexión** para trabajos de colegios
distintos: un trabajo del Los Álamos podría leer datos del San Antonio. Ese
es exactamente el fallo que este diseño evita.

Por eso `ContextoTenant::establecer()` **lanza excepción si no está dentro de
una transacción**.

### 1.4 Quién fija el contexto

Solo una clase: `App\Services\Tenancy\ContextoTenant`, y solo a través del
middleware `App\Http\Middleware\ResolverTenant`.

---

## 2. Las tablas sin RLS, y por qué

| Tabla | Por qué |
|---|---|
| `colegios` | Se consulta durante el acceso, cuando aún no hay sesión y no hay contexto que poner |
| `sessions` | La resuelve Laravel por identificador de sesión, no la aplicación |
| `migrations` | Infraestructura, no datos de nadie |

Es una excepción consciente, con dos compensaciones:

**Compensación 1: la vista `vw_colegios_publicos`.** Existe y expone **tres**
columnas (`slug`, `nombre`, `activo`) y solo colegios activos.

> **Corrección de 18-10-2026.** Este texto decía antes que «la aplicación lee
> esta vista». **Es falso: el código la crea y nunca la consulta.** El
> selector de acceso lee la tabla `colegios` directamente. Se detectó al
> auditar las 17 aplicaciones del estudio: solo Notaría, Agencia de viajes,
> Estudio de abogados y Control de Obras usan su vista de verdad.
> Queda pendiente decidir si se conecta o si se elimina; no se hizo ninguna
> de las dos cosas porque no había base de datos para probar el cambio.

**Compensación 2: el rol no es superusuario.** Quien tenga acceso SQL directo
puede leer `colegios` entera. Por eso el despliegue exige revisar permisos y
`colegio:verificar-aislamiento` lo comprueba.

**Riesgo residual, dicho con todas las letras:** un `slug` se puede enumerar
antes de autenticarse, porque es público (aparece en los enlaces que el
colegio reparte). Un atacante puede descubrir que el Los Álamos usa el sistema.
No puede ver más que el nombre.

---

## 3. Por qué no esquema por colegio

La alternativa clásica es un esquema de PostgreSQL por colegio
(`los_alamos.alumnos`, `san_antonio.alumnos`). Se evaluó y se descartó, y el
razonamiento es el mismo que en el portal de autoservicio:

1. **Las migraciones se multiplican.** Con 3 colegios son 3 ejecuciones. Con
   300, 300. Y cada ejecución es una oportunidad de que unas queden
   desalineadas.
2. **El pool de conexiones se vuelve cuello de botella.** Se dimensiona por
   combinaciones (esquema, rol) usadas de verdad.
3. **Laravel obliga a duplicar la lógica de esquema.** Cada consulta, cada
   prueba y cada cola necesita acordarse del esquema correcto.
4. **El aislamiento queda en manos de la aplicación.** El `search_path` es un
   parámetro de sesión. Si queda mal puesto (una conexión reutilizada del
   pool), la consulta va al esquema equivocado y **no falla**: devuelve datos
   del colegio equivocado sin error visible.
5. **Herramientas complicadas.** Respaldar un colegio sería `pg_dump -n
   los_alamos`, lo cual suena bien, pero obliga a scripts bespoke por colegio.

El punto 4 es el decisivo: es exactamente el fallo que RLS evita, porque en
RLS el colegio viaja **en la propia fila** y la política compara fila por fila.

---

# PARTE II — MODELO DE PERMISOS

## 4. El segundo filtro: dentro del colegio

La RLS garantiza que nada sale del colegio. Pero **dentro** del colegio hay
otro aislamiento que la base **no puede** hacer:

> La madre de Martina no puede ver a Sebastián, que va al mismo curso, en la
> misma lista, en el mismo colegio.

Para la base de datos son dos filas del mismo `tenant_id`. Ese filtro depende
del rol, y por eso es fácil de implementar mal: basta con que un controlador
se olvide de una línea.

### La solución: un solo lugar, no "acordarse de filtrar"

`App\Services\Alumnos\AlcanceDeAlumnos` es el **único** lugar donde se decide
a qué alumnos puede ver una persona. Todo endpoint que devuelva alumnos pasa
por ahí. No hay un segundo camino.

Esto no es una convención: es una restricción estructural. Si alguien
agrega un endpoint que arma su propia consulta de alumnos, se está saltando
el filtro por completo, y la revisión de código tiene que detectarlo.

### Las tres preguntas que responde

| Método | Pregunta | Quién pasa |
|---|---|---|
| `apoderablesPor($usuario)` | ¿A qué alumnos puede ver? | Todo listado |
| `puedeVerFichaCompleta($usuario, $alumno)` | ¿Puede ver TODO, incluido lo sensible? | Ficha individual |
| `puedeVerConducta($usuario, $alumno)` | ¿Puede leer los incidentes? | Módulo de conducta |

La tercera es separada a propósito: ver que existe un alumno y leer su
registro de conducta son dos permisos distintos, con personas distintas.

---

## 5. Los roles

### 5.1 Por qué un rol por persona y no permisos finos

En un colegio pequeño las personas tienen **cargos**, no capacidades
atomizadas. La secretaria que da de alta matrículas es "la secretaria", no
"alguien con permiso de escritura en matrículas".

Un modelo de permisos finos obligaría al colegio a inventar una taxonomía que
no entiende, y casi siempre terminaría **equivalente a un rol**, pero más
complicado de mantener y más fácil de equivocar.

Se eligió **rol por persona**, y dentro del rol lo que se separó a fondo es el
**dato sensible**.

### 5.2 Los seis roles

| Rol | Para quién es | Ve comunicaciones | Ve ficha completa | Ve conducta | Registra asistencia |
|---|---|---|---|---|---|
| `direccion` | Dirección del colegio | Sí | Sí, todos | Sí | No |
| `administracion` | Secretaría, admisión | Sí | Sí, todos | **No** | No |
| `docente` | Professor a cargo | Sí | Solo sus cursos | Solo sus cursos | **Sí** |
| `comunicaciones` | Maestro de comunicaciones | Sí | **No** | **No** | No |
| `orientacion` | Orientación, psicología | Sí | Solo sus cursos | Sí | No |
| `apoderado` | Familia | Solo las suyas | Solo sus hijos | **No** | No |

### 5.3 Las decisiones que hay que justificar

#### a) El maestro de comunicaciones NO ve fichas ni conducta

Es el rol que la ley obliga a mantener acotado.

**Justificación:** su trabajo es redactar y enviar avisos, y agendar
entrevistas. **Redactar un aviso no requiere conocer el hecho que lo
origina.** De hecho, conocerlo tiene un costo concreto: si el maestro sabe qué
pasó, el aviso deja de ser un documento administrativo y se vuelve una
herramienta de presión sobre la familia.

Por eso `AlcanceDeAlumnos::apoderablesPor()` devuelve **cero** alumnos para
este rol: puede mandar un aviso a un curso sin ver quién lo compone.

**Esta línea la puede mover el colegio.** Si un colegio con una sola persona
de comunicaciones quiere que también vea fichas, se cambia el enum. Está
documentado para que sea una decisión consciente y no un descuido.

#### b) Administración NO lee conducta

Dirección lee conducta, administración no.

**Justificación:** `administra()` responde "¿puede crear y editar
matrículas y catálogos?", no "¿puede leer el registro de incidentes?". Son
preguntas distintas, y el mismo rol suele responder distinto a las dos.

Además, con datos de menores, el principio de minimización va en la dirección
de dar el **menor** acceso posible: quien administra matrículas no necesita ver
un incidente de convivencia para poder matricular a alguien.

**Se invierte en `RolUsuario::leeConducta()`, que es configurable** (ver 5.4).

#### c) El apoderado NO lee conducta, ni la de su propio hijo

Esta es la decisión más discutible del modelo, y merece el párrafo completo.

**Qué recibe la familia:** la **comunicación**. El colegio redacta un aviso
que explica lo ocurrido, lo envía por el canal formal y queda registrado, con
acuse de recibo.

**Qué NO recibe:** el registro interno (`conducta_incidentes`), que contiene
la descripción cruda del incidente, la gravedad clasificada y la medida
aplicada.

**Por qué:** el registro interno es un instrumento de trabajo del colegio, no
un documento que se entrega. Es más granular que lo que el colegio quiere
comunicar (una pelea entre dos alumnos se registra una vez por alumno, y cada
versión tiene un enfoque distinto), y no está redactado para ser leído por
terceros.

Entregar el parte crudo sería, además, una decisión que **no corresponde al
software**: depende del reglamento interno del colegio y de su asesoría
legal.

**El derecho de la familia a conocer lo que ocurrió no se pierde**: se
satisface con la comunicación, que es el documento que el colegio emite con
su propia responsabilidad.

**Si el colegio decide distinto**, se cambia `AlcanceDeAlumnos::puedeVerConducta()`.

#### d) El apoderado SÍ ve la asistencia de su hijo

Lo contrario de (c), y por el mismo motivo de coherencia.

La hoja de asistencia es **un documento que la familia pide todos los meses**
y que la normativa escolar obliga a mantener y entregar. Ocultarla sería
inútil: la familia la pide igual, por otras vías.

Por eso `AsistenciaPolicy` le da acceso, y `IncidenteConductaPolicy` no.

### 5.4 Qué roles pueden leer conducta es configurable

```dotenv
COLEGIO_ROLES_CONDUCTA=direccion,orientacion,docente
```

Sale de `config('colegio.roles_que_leen_conducta')` y **no está fijo en el
código**.

Es configurable por un caso real: en un colegio chico la dirección y la
orientadora **suelen ser la misma persona**. Si el rol quedara fijo en el enum,
esa persona podría registrar un incidente y **no poder volver a leerlo**
—quedaría con el registro escrito pero ciego.

El valor por defecto es el más acotado. Cambiarlo es una decisión del colegio.

### 5.5 Quién puede administrar cuentas

Solo `direccion`.

Crear una cuenta con permiso de leer datos de menores es una decisión que no
se delega a la secretaría. `GestionAdministrativaPolicy::gestionarUsuarios()`
lo exige explícitamente.

---

## 6. Tabla resumen

| | Esquema por colegio (descartado) | Columna + RLS (elegido) |
|---|---|---|
| Migraciones | Una por colegio × N | Una sola |
| Pool de conexiones | Se dimensiona por colegio | Uno solo |
| Aislamiento | En la aplicación (`search_path`) | En la base de datos |
| Error de contexto | Falla en silencio | Falla ruidosamente |
| Complejidad en Laravel | Alta | Baja |

---

## 7. Verificación en el despliegue

`php artisan colegio:verificar-aislamiento` **falla con código 1** si:

1. el rol de la base es superusuario o tiene `BYPASSRLS`;
2. alguna de las 21 tablas no tiene RLS activada;
3. alguna no tiene RLS **forzada**;
4. alguna no tiene política;
5. `public.app_tenant_id()` **no falla** sin contexto;
6. con un colegio inexistente, alguna tabla devuelve filas;
7. el contexto queda colgando después de la transacción;
8. la vista pública expone más de tres columnas.

El `entrypoint.sh` lo corre antes de aceptar tráfico. Es preferible no
levantar antes que levantar abierto.

---

## 8. Las pruebas que lo comprueban

| Suite | Qué comprueba | Ubicación |
|---|---|---|
| `Aislamiento` | Que un colegio no vea datos de otro (lo impone la RLS) | `api/tests/Feature/Aislamiento/` |
| `Apoderado` | Que un padre no vea datos de otro alumno (**no lo impone la RLS**) | `api/tests/Feature/Apoderado/` |

Son dos suites separadas a propósito, porque son dos capas distintas. Es fácil
creer que la segunda está cubierta por la primera, y no lo está.

**Las pruebas están escritas y NO se ejecutaron.** Ver
[10 - Pruebas y estado](10-PRUEBAS-Y-ESTADO.md).

---

## 9. Los dos roles de PostgreSQL

La base de esta aplicación corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que el aislamiento entre colegios funcione en vez de
quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `colegio_admin` | `POSTGRES_USER` | Crear la base `colegio` y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `colegio_app` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

En este colegio el nombre de la aplicación se repite tal cual, con sufijo
`_app`. Conviene no confundirlos al leer el `.env.prod`: `DB_ADMIN_USERNAME` es
el de arranque y `DB_USERNAME` es el de la aplicación.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política de este documento se iba a aplicar: un
colegio habría visto las matriculas, los pagos y las evaluaciones de todos los
demás, sin error y sin aviso. El aislamiento entre colegios era decorativo.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE colegio OWNER TO colegio_app;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan colegio:verificar-aislamiento
```

El comando consulta `pg_roles` sobre el rol de la conexión y falla con código 1
si ese rol es superusuario o tiene `BYPASSRLS`, y después revisa que la RLS, las
políticas y el contexto de colegio protejan los datos de cada colegio.

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).