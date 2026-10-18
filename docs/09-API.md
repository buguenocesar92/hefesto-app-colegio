# 09 - API

Todos los endpoints cuelgan de `/api/v1` y exigen **sesión**, salvo el
ingreso, el callback y la salud.

---

## 1. Convenciones

| Tema | Regla |
|---|---|
| Prefijo | `/api/v1`, declarado en un solo lugar (`routes/web.php`) |
| Autenticación | Sesión de Laravel, cookie `HttpOnly` |
| CSRF | Cabecera `X-XSRF-TOKEN` |
| Formato | JSON en entrada y salida |
| Errores | `{ "mensaje": "..." }` y, en validación, `errors` por campo |
| Recurso inexistente o de otro colegio | **404**, nunca 403 |
| Tamaño de página | `per_page`, con tope en `COLEGIO_POR_PAGINA_MAXIMO` |
| Filtros | Siempre validados contra un catálogo; nunca se interpolan |

### Por qué 404 y no 403

Un 403 ("no tiene permiso") **confirma que el recurso existe**. Para quien está
probando identificadores, eso es enumeración. La RLS hace que el recurso no sea
visible para el otro colegio, así que 404 es además la respuesta correcta.

---

## 2. Autenticación

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | `/api/v1/auth/login` | Redirige a Authentik con PKCE |
| `GET` | `/api/v1/auth/callback` | Vuelve de Authentik y abre sesión |
| `POST` | `/api/v1/auth/logout` | Cierra sesión e invalida el identificador |
| `GET` | `/api/v1/auth/usuario` | Quién está conectado y qué puede hacer |

`/auth/usuario` devuelve `capacidades`, que la interfaz usa para mostrar
menús. **No autoriza nada**: cada endpoint vuelve a comprobar el permiso.

```json
{
  "usuario": {
    "id": "…",
    "nombre_completo": "Javiera Contreras",
    "rol": "comunicaciones",
    "rol_etiqueta": "Comunicaciones"
  },
  "colegio": { "nombre": "Colegio Los Álamos", "slug": "colegio-los-alamos" },
  "capacidades": {
    "administra": false,
    "ve_todos_los_alumnos": false,
    "registra_asistencia": false,
    "registra_conducta": false,
    "ve_conducta": false,
    "envia_comunicaciones": true,
    "gestiona_agenda": true
  }
}
```

---

## 3. Alumnos

| Método | Ruta | Quién |
|---|---|---|
| `GET` | `/api/v1/alumnos` | Cualquiera con sesión (con alcance) |
| `GET` | `/api/v1/alumnos/{alumno}` | **Exige `verFichaCompleta`** |

`GET /alumnos` devuelve el listado acotado por `AlcanceDeAlumnos`: un
apoderado ve solo sus hijos, un docente los de sus cursos, el maestro de
comunicaciones **ninguno**.

`GET /alumnos/{id}` es un permiso **más restrictivo**: ver que un alumno
existe no autoriza a abrir su ficha. Ahí van fecha de nacimiento, dirección,
apoderados y contactos de emergencia.

Filtros: `curso_id`, `estado`, `q`, `per_page`, `page`.

---

## 4. Matrículas

| Método | Ruta | Quién |
|---|---|---|
| `GET` | `/api/v1/matriculas` | Dirección y administración |
| `POST` | `/api/v1/matriculas` | Dirección y administración |
| `PATCH` | `/api/v1/matriculas/{id}/estado` | Dirección y administración |

`POST` acepta `numero_matricula` o lo propone. Si el número ya existe en el
colegio y año, **falla con un mensaje claro** en vez de guardar una referencia
repetida.

No se borran: se cambian de estado. El registro de que el alumno estuvo ahí
importa.

---

## 5. Comunicaciones (el módulo central)

| Método | Ruta | Quién |
|---|---|---|
| `GET` | `/api/v1/comunicaciones` | Roles del colegio |
| `POST` | `/api/v1/comunicaciones` | Roles que envían |
| `GET` | `/api/v1/comunicaciones/{id}` | Destinatarios y colegio |
| `POST` | `/api/v1/comunicaciones/{id}/enviar` | Roles que envían |
| `GET` | `/api/v1/comunicaciones/{id}/recibos` | **Solo colegio** |
| `POST` | `/api/v1/comunicaciones/{id}/recibos` | Destinatario o colegio |

### Crear

```json
{
  "titulo": "Reunión de apoderados de 3° Básico",
  "cuerpo": "Se extiende la invitación…",
  "canal": "correo",
  "tipo_destinatario": "curso",
  "curso_id": "…",
  "requiere_acuse": true,
  "enviar": true
}
```

`tipo_destinatario` acepta `curso`, `nivel`, `apoderado` y `alumno`.

**La validación exige que el identificador corresponda al tipo**, y rechaza
los que sobren. Un aviso de tipo `curso` con `nivel_id` y sin `curso_id` se
rechaza: un endpoint que acepta campos que no usa es un endpoint que después
se lee mal.

### Enviar resuelve los destinatarios

Los destinatarios **se calculan al enviar**, no antes, y quedan escritos en
`comunicacion_destinatarios`. De ahí sale el "¿nos avisaron esto?" dentro de
seis meses.

Si no se encuentra ninguna familia, **falla con un error**. Un aviso que no
salió, con la impresión de que sí, es peor que no mandar el aviso.

Una familia con dos hijos en el mismo curso recibe **un** aviso, no dos.

### Acuses

```json
{
  "medio": "web",
  "recibido_en": null,
  "apoderado_id": "…"
}
```

`medio` es obligatorio: la diferencia entre "lo confirmó en el portal" y "lo
confirmó por teléfono" es la que sirve para responder a una familia.

**Solo acepta un acuse de una familia que fue destinataria** de ese aviso.
Confirmar dos veces actualiza la fecha, no duplica la fila.

### Los acuses no se ven entre apoderados

`GET /comunicaciones/{id}/recibos` es **solo para el colegio**: el acuse es
evidencia de la relación con una familia concreta.

Un apoderado ve las comunicaciones dirigidas a su familia, y el resumen de
entrega no aparece en su respuesta.

---

## 6. Entrevistas y actas

| Método | Ruta |
|---|---|
| `GET` | `/api/v1/entrevistas` |
| `POST` | `/api/v1/entrevistas` |
| `GET` | `/api/v1/entrevistas/{id}` |
| `PATCH` | `/api/v1/entrevistas/{id}/estado` |
| `GET` | `/api/v1/entrevistas/{id}/acta` |
| `POST` | `/api/v1/entrevistas/{id}/acta` |

Filtros de la agenda: `desde`, `hasta`, `estado`, `per_page`.

Solo se puede registrar **una** acta por entrevista. El motivo está en el
código, no en la base: el mensaje de error lo escribe la validación.

---

## 7. Asistencia y conducta

**Rutas y permisos separados a propósito.**

| Método | Ruta | Registra | Lee |
|---|---|---|---|
| `GET` | `/api/v1/asistencias` | — | Colegio y **apoderado de su hijo** |
| `POST` | `/api/v1/asistencias` | Solo `docente` | — |
| `GET` | `/api/v1/conducta` | — | Solo roles con permiso de conducta |
| `POST` | `/api/v1/conducta` | `docente` y `orientacion` | — |

La asimetría es deliberada: la **familia ve la asistencia** (es un documento
que pide todos los meses) y **no ve el registro de conducta** (es un
instrumento de trabajo del colegio). Ver
[03](03-MULTITENANCY-Y-ROLES.md), sección 5.3.

### Registrar asistencia de un curso

```json
{
  "fecha": "2026-03-10",
  "curso_id": "…",
  "registros": [
    { "alumno_id": "…", "estado": "presente" },
    { "alumno_id": "…", "estado": "ausente" },
    { "alumno_id": "…", "estado": "justificado", "justificacion": "Licencia médica." }
  ]
}
```

Recibe una lista porque el caso real es la hoja completa de un curso.

**El alcance se aplica también al escribir.** Si el docente manda el id de un
alumno que no es suyo, esa fila se **omite** y la respuesta dice cuántas
quedaron fuera. No es un error del sistema: es un dato que no corresponde.

### Registrar un incidente

```json
{
  "alumno_id": "…",
  "curso_id": "…",
  "fecha": "2026-03-10",
  "categoria": "convivencia",
  "gravedad": "media",
  "descripcion": "Participó en una discusión con un compañero durante el recreo.",
  "medida": "Se cita a la familia para entrevista."
}
```

La respuesta **recuerda lo que la gravedad obliga**:

> "Esta gravedad requiere avisar a la familia: cree la comunicación y
> enlacela al incidente."

El sistema no manda el aviso: **eso es una decisión de una persona.**

---

## 8. Administración

| Método | Ruta |
|---|---|
| `GET`/`POST` | `/api/v1/admin/niveles` |
| `GET`/`POST` | `/api/v1/admin/cursos` |
| `GET`/`POST` | `/api/v1/admin/profesionales` |
| `GET`/`POST` | `/api/v1/admin/plantillas` |
| `GET`/`POST` | `/api/v1/admin/apoderados` |
| `GET`/`POST` | `/api/v1/admin/consentimientos` |
| `POST` | `/api/v1/admin/consentimientos/{id}/revocar` |

Todo exige `direccion` o `administración`, salvo la gestión de cuentas, que
solo la puede hacer `direccion`.

**Revocar un consentimiento no lo borra**: deja `revocado_en`. Un registro que
desaparece al revocarse no sirve para demostrar que hubo un período
autorizado.

---

## 9. Lo que NO hace esta API

- **No envía nada.** Registrar una comunicación significa "esto ya se mandé".
- **No tiene endpoint de calificaciones.**
- **No sube archivos** desde la interfaz.
- **No tiene paginación por cursor.** Es `offset` con tope de tamaño.

### Enviar de verdad: por qué no está

Enviar por correo o WhatsApp de verdad requiere:

1. un contrato con un proveedor;
2. **consentimiento del titular** para comunicaciones comerciales;
3. manejo de rebotes y reintentos;
4. decidir qué pasa cuando un aviso legal no llega.

Ese módulo es **otro proyecto**, con otro alcance. La primera versión registra
lo que el colegio ya envió por el canal que ya usa, y deja constancia.

Registrar un aviso por WhatsApp y mandarlo son dos cosas distintas, y
confundirlas es un riesgo: un sistema que promete enviar y no manda es peor que
uno que solo registra.

---

## 10. Ejemplo completo

```bash
API=http://localhost:8000/api/v1
COOKIE=archivo-de-cookies.txt

# 1. Entrar
curl -c "$COOKIE" -b "$COOKIE" "$API/auth/login"

# 2. Ver quién está conectado
curl -c "$COOKIE" -b "$COOKIE" "$API/auth/usuario"

# 3. Crear y enviar un aviso al curso
TOKEN=$(grep XSRF-TOKEN "$COOKIE" | awk '{print $7}')

curl -c "$COOKIE" -b "$COOKIE" -X POST "$API/comunicaciones" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $TOKEN" \
  -d '{
    "titulo": "Cambio de horario",
    "cuerpo": "Las clases de educación física se movieron al primer bloque.",
    "canal": "whatsapp",
    "tipo_destinatario": "curso",
    "curso_id": "ID",
    "enviar": true
  }'

# 4. Ver quién confirmó
curl -c "$COOKIE" -b "$COOKIE" "$API/comunicaciones/ID/recibos"
```