# Gestión de Colegio

Aplicación propia de **Hefesto Studio**: matrícula, comunicación con las
familias y seguimiento de alumnos para un colegio chico.

> **Este proyecto es software original de Hefesto.** No es un kit de despliegue
> de una herramienta de terceros. Se usó Laravel, Nuxt, `firebase/php-jwt` y
> Authentik como librerías, que es lo normal: lo que se escribió aquí es la
> aplicación.

---

# ADVERTENCIA

## Esto no está listo para matricular alumnos reales.

El código de este proyecto **no se ejecutó**. No hay ni una línea de esta
aplicación corriendo, probada o validada: no se compiló el PHP, no se corrieron
las migraciones, no se levantó PostgreSQL, no se ejecutaron las pruebas y no se
construyó la interfaz.

**El tratamiento de datos de menores y de sus familias tiene obligaciones
legales que el sistema no reemplaza.** La Ley 19.628, modificada por la Ley
20.720, considera **dato sensible** el vinculado a menores de edad, y hay
obligaciones adicionales en la Ley 21.719 cuyo contenido **no se verificó**
contra el texto oficial. El consentimiento de las familias, la evidencia de
que se obtuvo, la definición de cuánto tiempo se conserva cada dato y la
respuesta a los requerimientos de los titulares **son responsabilidades del
colegio con su asesoría legal**, no del software.

Agregar a eso: **no hubo auditoría de seguridad, ni pruebas de penetración, ni
revisión legal externa.**

Lo que sí se hizo está documentado, incluida la separación entre lo que se
comprobó y lo que no:
**[docs/10-PRUEBAS-Y-ESTADO.md](docs/10-PRUEBAS-Y-ESTADO.md)**.

---

## Estado

**Primer corte. Código escrito, nada ejecutado.**

El detalle exacto de lo que se verificó y lo que no está en
[docs/10](docs/10-PRUEBAS-Y-ESTADO.md), que es el primer documento que hay
que leer.

---

## El problema que resuelve

Un colegio chico hoy tiene la información en tres lugares: quién entra y cuándo
(cuaderno), cómo va cada alumno (la cabeza de la profesora) y qué le avisó a
cada familia (un grupo de WhatsApp que nadie puede buscar).

El costo real está en el último punto. Cuando una familia pregunta
"¿qué le avisaron a mi hijo?", y nadie encuentra el registro, la recepción
pierde una hora y la relación se desgasta. Y cuando una comunicación
importante se pierde en WhatsApp, el colegio tiene un problema **que no puede
probar**.

Por eso el corazón de esta aplicación es **el registro de comunicaciones**:
fecha, emisor, destinatarios, canal y contenido, con acuse de recibo cuando
corresponde. Eso convierte "dijimos que no" en "está aquí, y el padre lo
recibió".

---

## Lo que hace

1. **Comunicaciones**: avisos a un curso o a un nivel, con la lista de
   familias que los recibieron y cuáles confirmaron lectura. Registro de
   acuses con fecha, medio e IP.
2. **Matrícula y alumnos**: ficha, apoderados, contactos de emergencia,
   matrícula con referencia por año y estado.
3. **Asistencia y conducta**: **tablas y permisos separados**, porque no son
   lo mismo (ver más abajo).
4. **Entrevistas y reuniones**: agenda con motivo, y acta de lo acordado.
5. **Administración**: niveles, cursos, profesionales, plantillas de
   comunicación y registro de consentimientos.

## Lo que NO hace

- **No envía nada.** Registrar un aviso por WhatsApp significa "esto ya se
  mandó". El envío real es otro módulo. Ver
  [docs/09](docs/09-API.md).
- No tiene calificaciones.
- No sube archivos desde la interfaz.
- **No reemplaza el cumplimiento legal** de la Ley 19.628.

---

## Las dos decisiones que explican el resto

### 1. El aislamiento entre colegios lo impone la base de datos

Todas las tablas de negocio llevan `tenant_id` y hay políticas de **Row Level
Security** que impiden que una consulta devuelva filas de otro colegio. El
colegio viaja con **alcance de transición**, y la función que lo lee **lanza
excepción** si no hay contexto: el aislamiento **falla cerrado**.

Es el mismo patrón ya decidido y documentado en
`apps/portal-autoservicio/docs/03-MULTITENANCY.md`, y está reutilizado sin
cambios de enfoque.

### 2. Asistencia y conducta son tablas y permisos distintos

No porque sí, sino porque **mezclarlas rompe el sistema de permisos**:

- la **familia** ve la asistencia de su hijo (es un documento que pide todos
  los meses) y **no ve** el registro de incidentes de conducta;
- el **docente** marca presencia y registra hechos;
- el **maestro de comunicaciones** redacta avisos y **no ve ni fichas ni
  conducta**: mandar un aviso no requiere conocer el hecho que lo origina.

Si estuvieran en una sola tabla, proteger la conducta obligaría a proteger la
asistencia. Son dos migraciones, dos modelos, dos Policies, dos endpoints y
dos suites de pruebas.

El detalle está en [docs/02](docs/02-MODELO-DE-DATOS.md) y en
[docs/03](docs/03-MULTITENANCY-Y-ROLES.md).

---

## Estructura

```
apps/colegio/
├── README.md
├── .env.example
├── docs/       # diez documentos, ver abajo
├── api/        # Laravel 13: modelos, migraciones, RLS, policies, pruebas
├── web/        # Nuxt 4 con SSR y el BFF que reenvía cookies
├── deploy/     # Dockerfiles, compose de desarrollo y producción, Caddy
└── herramientas/  # verificación que no necesita PHP ni Node
```

---

## Documentación

Empieza por **[docs/10-PRUEBAS-Y-ESTADO.md](docs/10-PRUEBAS-Y-ESTADO.md)**.

| Documento | De qué trata |
|---|---|
| [01 - Arquitectura](docs/01-ARQUITECTURA.md) | Las piezas, el BFF y el orden de los middleware |
| [02 - Modelo de datos](docs/02-MODELO-DE-DATOS.md) | Las tablas y por qué asistencia y conducta están separadas |
| [03 - Multitenancy y roles](docs/03-MULTITENANCY-Y-ROLES.md) | **El central.** Aislamiento y modelo de permisos, con su justificación |
| [04 - Instalación](docs/04-INSTALACION.md) | Paso a paso, incluido el paso del `authentik_sub` |
| [05 - Configuración](docs/05-CONFIGURACION.md) | Cada variable y qué pasa si está mal |
| [06 - Primera matrícula](docs/06-PRIMERA-MATRICULA.md) | Recorrido completo hasta la primera comunicación |
| [07 - Seguridad y Ley 19.628](docs/07-SEGURIDAD-Y-LEY-19628.md) | Datos de menores, lo verificado y lo pendiente |
| [08 - Respaldos](docs/08-RESPALDOS.md) | Cómo respaldar y **cómo probar que el respaldo sirve** |
| [09 - API](docs/09-API.md) | Endpoints y alcance |
| [10 - Pruebas y estado](docs/10-PRUEBAS-Y-ESTADO.md) | **Qué se ejecutó y qué quedó sin probar** |

---

## Puesta en marcha

Requiere Docker con Compose v2. **No hace falta** PHP, Composer, Node ni
PostgreSQL en el equipo.

```bash
cp .env.example .env
# generar los secretos y pegarlos (ver docs/04)

docker compose -f deploy/docker-compose.dev.yml --env-file .env up -d

# Migraciones y verificación OBLIGATORIA del aislamiento
docker compose -f deploy/docker-compose.dev.yml exec api php artisan migrate
docker compose -f deploy/docker-compose.dev.yml exec api php artisan colegio:verificar-aislamiento

# Datos de ejemplo (dos colegios, para poder comprobar el aislamiento)
docker compose -f deploy/docker-compose.dev.yml exec api php artisan migrate --seed
```

> **Nada de esto se ha ejecutado.** El procedimiento está escrito con el
> detalle suficiente para seguirlo, y esa es exactamente su limitación.

---

## Stack

| Pieza | Tecnología |
|---|---|
| API | Laravel 13 / PHP 8.4 |
| Base de datos | PostgreSQL 18.6 (con Row Level Security) |
| Interfaz | Nuxt 4.6 / Node 24 |
| Cache y colas | Redis 8.8 |
| Identidad | Authentik 2025.10 (OIDC) |
| Entrada pública | Caddy 2.11 (HTTPS automático) |

Las versiones son las mismas que el portal de autoservicio de Hefesto, que sí
las verificó contra Packagist, npm y Docker Hub. **No se re-verificaron para
este proyecto**, y faltan fijarlas por digest antes de producción.

---

## Seguridad

Si encuentra un problema de seguridad, no abra un ticket público. Escriba
directamente a Hefesto Studio.

Los puntos abiertos conocidos están en
[docs/07](docs/07-SEGURIDAD-Y-LEY-19628.md), sección 4. Los dos más graves:

1. **Las pruebas están escritas y no se ejecutaron**, y el SQL de las
   migraciones nunca fue validado por PostgreSQL.
2. **El análisis de la Ley 21.719 está incompleto**: se verificó que la ley
   existe y que modifica la 19.628, pero no se leyó el texto consolidado.