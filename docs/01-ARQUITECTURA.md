# 01 - Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [03](03-MULTITENANCY-Y-ROLES.md).

Este documento explica qué piezas tiene el sistema, cómo se relacionan y por
qué están separadas así.

---

## 1. El problema que viene primero

Un colegio chico hoy tiene la información en tres lugares distintos: quién
entra y cuándo (cuaderno o planilla), cómo va cada alumno (la cabeza de la
profesora), y qué le avisó a cada familia (un grupo de WhatsApp que nadie
puede buscar).

El costo real no está en el desorden. Está en el último punto:

> Cuando una familia pregunta "¿qué le avisaron a mi hijo?", y nadie encuentra
> el registro, la recepción pierde una hora y la relación se desgasta.

Y en el peor caso, cuando una comunicación importante se pierde en un grupo
de WhatsApp, el colegio tiene un problema que **no puede probar**.

Por eso el módulo central de esta aplicación no es la matrícula ni la
asistencia: es el **registro de comunicaciones**, con acuse de recibo. Todo lo
demás existe alrededor de ese módulo.

---

## 2. Las piezas

| Pieza | Tecnología | De qué se ocupa |
|---|---|---|
| `api/` | Laravel 13 / PHP 8.4 | Reglas de negocio, autorización, datos |
| `web/` | Nuxt 4 / Node 24 | Interfaz y BFF hacia la API |
| `deploy/` | Docker Compose | Ejecución de los servicios |
| Authentik | OIDC | Identidad: demuestra quién es la persona |
| PostgreSQL 18.6 | RLS | Aislamiento entre colegios |
| Redis 8.8 | Cache y colas | Descubrimiento OIDC, trabajos en segundo plano |

---

## 3. Arquitectura BFF

La interfaz es un "backend for frontend": **el navegador nunca habla directo
con la API**.

```
navegador ──► Nuxt (BFF) ──► API Laravel ──► PostgreSQL
                 │               │
                 └─ reenvía cookies y CSRF
```

### Por qué existe esta capa

La sesión vive en una cookie `HttpOnly` + `Secure` + `SameSite=Lax`. Si el
navegador llamara a la API directamente, que está en **otra origen**, la
cookie no viajaría y el problema tendría que resolverse con CORS y
credenciales. Eso, además de ser más código, **duplica el riesgo de CSRF**.

Al pasar todo por el mismo origen:

- la cookie se mantiene en una sola origen;
- el token CSRF viaja como cabecera `X-XSRF-TOKEN`, que es lo que Laravel
  espera;
- el navegador nunca ve la dirección interna de la API.

El reenvío está en `web/server/api/[...ruta].ts`.

---

## 4. Cómo entra una petición

El orden de los middleware en el grupo `web` es lo importante:

```
petición
   │
   ├─ 1. Sesión de Laravel (cookie)
   │
   ├─ 2. Verificación CSRF
   │
   ├─ 3. ResolverTenant      ← fija el colegio y abre una transacción
   │
   ├─ 4. AuditarAcceso       ← anota en la bitácora (después de responder)
   │
   ├─ 5. auth                ← exige sesión
   │
   └─ 6. Controlador
            │
            ├─ $this->authorize(...)   ← Policy: qué puede hacer esta persona
            │
            └─ Modelo                   ← la RLS filtra por colegio
```

**Por qué `ResolverTenant` va antes que `AuditarAcceso`:** la bitácora escribe
en `bitacora_accesos`, que tiene RLS y necesita el contexto del colegio ya
fijado. Al revés, el registro fallaría por falta de contexto.

**Por qué `ResolverTenant` va antes que `auth`:** el middleware resuelve el
usuario por su cuenta con el guard `web` (que ya lee la sesión) y necesita el
colegio ANTES de que el controlador corra.

---

## 5. Doble filtro

Hay **dos** capas de autorización, y no es redundante:

| Capa | Dónde | Qué decide |
|---|---|---|
| Row Level Security | PostgreSQL | De qué colegio son los datos |
| Policies | Laravel | Qué puede hacer la persona dentro de su colegio |

Cada una tapa un agujero de la otra:

- **Solo RLS:** un padre vería todos los alumnos de su colegio.
- **Solo Policy:** un padre podría abrir por URL la ficha de un alumno de
  otro colegio.

La regla de `AlcanceDeAlumnos` (docs/03) es la que decide lo segundo.

---

## 6. Módulos y dónde vive cada uno

| Módulo | Tablas principales | Endpoints |
|---|---|---|
| Matrícula y alumnos | `alumnos`, `matriculas`, `apoderados`, `apoderado_alumnos`, `contactos_emergencia` | `/alumnos`, `/matriculas` |
| **Comunicaciones** | `comunicaciones`, `comunicacion_destinatarios`, `comunicacion_recibos`, `plantillas_comunicacion` | `/comunicaciones` |
| Entrevistas y reuniones | `entrevistas`, `actas_reunion` | `/entrevistas` |
| Asistencia y conducta | `asistencias`, `conducta_incidentes` | `/asistencias`, `/conducta` |
| Administración | `niveles`, `cursos`, `curso_profesional`, `profesionales`, `consentimientos`, `usuarios` | `/admin/*` |

---

## 7. Lo que este sistema NO es

Conviene decirlo temprano, porque es donde aparecen las malas expectativas:

- **No envía nada.** Registrar una comunicación por WhatsApp significa
  "esto ya se mandó". El envío real requiere un contrato con un proveedor y es
  un módulo aparte (docs/09).
- **No es un sistema de calificaciones.** Se indujeron al módulo de
  seguimiento porque el enunciado habla de "cómo va cada alumno", pero el
  núcleo es asistencia, conducta y comunicación, no notas.
- **No reemplaza el cumplimiento legal.** Tratar datos de menores tiene
  obligaciones que el software no puede cumplir por sí solo (docs/07).

---

## 8. Documentos que hay que leer, en orden

| Documento | De qué trata |
|---|---|
| [02 - Modelo de datos](02-MODELO-DE-DATOS.md) | Las tablas y por qué asistencia y conducta están separadas |
| [03 - Multitenancy y roles](03-MULTITENANCY-Y-ROLES.md) | **El central.** Aislamiento y modelo de permisos |
| [07 - Seguridad y Ley 19.628](07-SEGURIDAD-Y-LEY-19628.md) | Datos de menores y obligaciones legales |
| [10 - Pruebas y estado](10-PRUEBAS-Y-ESTADO.md) | **Qué se ejecutó y qué no.** Leer antes de comprometer una fecha |