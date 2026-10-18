# 10 - Pruebas y estado

Este documento separa, sin adornos, lo que **se comprobó** de lo que **solo se
escribió**. Es el primero que hay que leer antes de comprometer una fecha.

---

## 1. Entorno disponible

Al momento de escribir este proyecto, en la máquina de trabajo:

| Herramienta | Estado |
|---|---|
| PHP | **No disponible** |
| Composer | **No disponible** |
| Docker | **No disponible** |
| psql (cliente) | **No disponible** |
| Node.js | Disponible |
| Python | Disponible |
| Git | Disponible |
| Acceso a internet | Disponible |

Por lo tanto:

> **No se pudo ejecutar Laravel, ni las migraciones, ni PHPUnit, ni un arranque
> de PostgreSQL con RLS, ni una construcción de Nuxt, ni un `docker compose`.**

El estado del proyecto es **"código escrito, sin ejecutar"**.

---

## 2. Lo que SÍ se ejecutó

### 2.1 Barrido de caracteres fuera del español

Con `herramientas/verificar_caracteres.py`, que busca cirílico, CJK, árabe,
hebreo, devanagari y otros alfabetos:

| Pasada | Resultado |
|---|---|
| Primera | **Más de 20 hallazgos**, todos corregidos (fragmentos de otros alfabetos en comentarios, cadenas y documentación) |
| Segunda | 0 hallazgos |
| Tercera (al cerrar) | 0 hallazgos, sobre 159 archivos revisables |

**Nota honesta sobre este control:** el barrido detecta alfabetos no latinos,
pero **no detecta palabras en inglés coladas dentro de texto en español**
("discharging", "accuse", "Requirements"). Esas se corrigieron a mano, y no hay
ninguna herramienta que las busque automáticamente. Es un control real pero
parcial.

### 2.2 Verificaciones de estructura

| Verificación | Resultado |
|---|---|
| Balance de delimitadores en PHP, TS, Vue, JSON, XML, YAML | Sin desbalances |
| JSON válido (`composer.json`, `package.json`) | 2 de 2 válidos |
| YAML válido (`docker-compose.dev.yml`, `docker-compose.prod.yml`) | 2 de 2 válidos |
| Coherencia de `tenant_id` entre migraciones, RLS, modelos y Policy | Revisado a mano, coincide en las 21 tablas |
| Referencias ruta ↔ controlador | Todas resueltas; ningún controlador huérfano |
| Referencias modelo ↔ tabla | Coherentes |
| Secretos en `.env.example` | Ninguno; solo nombres de variable |

> Estas verificaciones detectan desbalances de sintaxis y caracteres. **No
> compilan el código.** Un archivo balanceado puede tener todavía un error de
> tipo o de nombre de clase.

### 2.3 Verificación documental

- **Ley 19.628** (protección de la vida privada): confirmada en el registro de
  la Biblioteca del Congreso Nacional.
- **Ley 20.720**: confirmada según la descripción oficial de la BCN, que indica
  que incorpora "como dato sensible, aquel vinculado a menores de edad".
- **Ley 21.719**: se verificó que existe y que modifica la Ley 19.628.
  **NO se leyó el texto consolidado.** Marcado como pendiente en
  [07](07-SEGURIDAD-Y-LEY-19628.md).
- **Stack**: las versiones se tomaron de las mismas referencias usadas en el
  portal de autoservicio, que sí las verificó contra Packagist, npm y Docker
  Hub. **No se re-verificaron acá**, y el número de versión exacto puede haber
  cambiado desde entonces.

---

## 3. Lo que NO se ejecutó

**Nada de esto se hizo.**

### 3.1 PHP

- **No se compiló ni un solo archivo PHP** con `php -l`. El verificador de
  balance es un proxy, no un compilador.
- **No se instalaron las dependencias** (`composer install`).
- **No se arrancó Laravel.**
- **No se corrieron las migraciones.** El SQL de las 20 migraciones, la función
  `plpgsql`, las 21 políticas y la vista **no han sido validados por
  PostgreSQL**.

### 3.2 Pruebas automáticas

Las pruebas están escritas y **no se ejecutaron ninguna**:

| Suite | Ubicación | Estado |
|---|---|---|
| Unitarias (reglas de negocio) | `api/tests/Unit/` | Escritas, **no ejecutadas** |
| Aislamiento entre colegios | `api/tests/Feature/Aislamiento/` | Escritas, **no ejecutadas** |
| **Alcance del apoderado** | `api/tests/Feature/Apoderado/` | Escritas, **no ejecutadas** |
| Comunicaciones y acuses | `api/tests/Feature/Comunicaciones/` | Escritas, **no ejecutadas** |
| Asistencia y conducta | `api/tests/Feature/Registro/` | Escritas, **no ejecutadas** |

Las de **aislamiento** y las de **apoderado** son las que hay que correr
primero, por el orden en que está escrito el sistema.

Requieren **PostgreSQL de verdad**, no SQLite: el aislamiento está
implementado con Row Level Security, y en SQLite pasarían sin comprobar nada.

### 3.3 Frontend

- **No se ejecutó `npm install`.**
- **No se corrió `nuxt build`.**
- **No se corrió `nuxt typecheck`.** Los tipos de `app/tipos/api.ts` son una
  copia **manual** de lo que devuelven los Resources: pueden no coincidir, y
  `typecheck` no detectaría esa diferencia.
- **No se probó el BFF** contra una API real.

### 3.4 Seguridad

- **No hay auditoría de seguridad.**
- **No hay pruebas de penetración.**
- **No hay revisión legal externa.** El análisis de la Ley 21.719 está
  incompleto a propósito, y está marcado como pendiente.
- El BFF reenvía `set-cookie` usando `getSetCookie()` cuando existe y un
  fallback cuando no. **No se verificó** contra la versión de `h3` que
  instala Nuxt 4.

### 3.5 Despliegue

- **No se construyó ninguna imagen.**
- **No se levantó `docker compose`.**
- **No se generó ningún certificado TLS.**
- **No se probó un respaldo ni una restauración.**

---

## 4. Riesgos abiertos

Ordenados por lo que duelen si salen mal:

### 1. El SQL nunca fue validado por PostgreSQL

**Es el riesgo más grave.** Veinte migraciones, una función `plpgsql`, 21
políticas y una vista, escritas sin poder ejecutarlas.

Si hay un error de sintaxis o de lógica en la migración de RLS, **el
aislamiento se cae entero y la aplicación no da ningún aviso visible**: la
interfaz se ve perfecta y muestra datos de otro colegio.

Mitigación: `php artisan colegio:verificar-aislamiento` detecta el problema, y
el `entrypoint.sh` aborta el arranque. **Pero ese comando tampoco se probó.**

### 2. Las pruebas de aislamiento no se ejecutaron

Si hay un error en el SQL, las pruebas lo detectarían. Como no se ejecutaron,
ese error sigue ahí, sin saber.

### 3. El frontend no compiló

Los tipos son manuales y el BFF usa funciones de `h3` cuya versión no se
confirmó. Puede haber errores de compilación que nadie ha visto.

### 4. La configuración de producción tiene un hueco conocido

El compose de producción no limpia `config:cache`. Con la configuración
cacheada, una variable cambiada **no surte efecto** hasta que se limpie la
caché. Hay que agregarlo al procedimiento de despliegue.

### 5. Las imágenes no están fijadas por digest

Solo por tag exacto (`8.4.26`, `18.6`, `2025.10`). Un tag exacto es
reproducible casi siempre; un digest, siempre. Además, la imagen `composer:2`
**no está fijada de ninguna manera**.

### 6. Sin límites de tasa

El endpoint de autenticación es el primero que se ataca. Ver
[07](07-SEGURIDAD-Y-LEY-19628.md).

### 7. La `Content-Security-Policy` está incompleta

La de Caddy no contempla los scripts con nonce que inyecta Nuxt. Antes de
producción hay que ajustarla, y ajustarla a ciegas rompe la aplicación.

### 8. La tabla `colegios` sin RLS

Es a propósito y está compensado, pero quien tenga SQL lee la razón social, el
RUT y los contactos de todos los colegios.

### 9. El análisis legal está incompleto

El contenido de la Ley 21.719 no se verificó. No se puede afirmar que el
sistema cumple con ella; solo que no se encontró una contradicción.

---

## 5. Orden recomendado para retomar el trabajo

En este orden, porque cada paso desbloquea el siguiente:

1. `composer install` y `php -l` sobre todos los archivos de `api/`.
   *(Un error de sintaxis es lo más probable y lo más rápido de encontrar.)*
2. Levantar PostgreSQL y correr `php artisan migrate`.
   *(Acá se valida el riesgo número 1.)*
3. `php artisan colegio:verificar-aislamiento`. Debe pasar.
4. `php artisan test --testsuite=Aislamiento`. Debe pasar.
5. `php artisan test --testsuite=Apoderado`. Debe pasar.
6. El resto de las pruebas.
7. `npm install && npm run typecheck && npm run build` en `web/`.
8. Levantar el compose de desarrollo y recorrer el flujo completo con dos
   colegios reales en Authentik.
9. **Recién ahí**, hablar con la asesoría legal del colegio sobre la Ley
   21.719 y el documento de consentimiento.
10. Recién ahí, decidir sobre producción.

Los pasos 1 a 8 los puede hacer Hefesto. Los pasos 9 y 10 **no**: son del
colegio.

---

## 6. Resumen en una línea

Hay 166 archivos escritos, revisados y consistentes entre sí, con la
documentación de por qué cada decisión se tomó así, **y cero líneas de código
ejecutadas**.

La parte que más importa —el aislamiento y los permisos— está diseñada con el
mismo patrón ya usado y documentado en otro proyecto de Hefesto, está
documentada para revisión, y **está sin probar**. Eso es exactamente lo que hay
que saber antes de poner un alumno real en esto.

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`colegio_admin`) y el rol de la
aplicación (`colegio_app`) está escrita en el `compose`, en
`deploy/postgres/inicializar-rol.sh` y en
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md). **No se ha ejecutado
ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `colegio_app` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS.
3. Correr `php artisan colegio:verificar-aislamiento` y confirmar que sale con
   código 0.
4. Probar el escenario que motiva todo el cambio: un colegio intentando leer
   matrículas o evaluaciones de otro.

Lo único verificado es estático: `bash -n` pasa sobre el script y el `compose`
es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`. El
razonamiento se apoya en la documentación oficial de PostgreSQL 18, no en una
ejecución.