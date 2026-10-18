# 06 - Primera matrícula, paso a paso

Este es el recorrido completo desde que el colegio decide usar el sistema
hasta que puede responder "¿qué le avisaron a mi hijo?".

Asume que [04 - Instalación](04-INSTALACION.md) ya se completó.

---

## 1. Orden de arranque

El orden importa, porque cada paso depende del anterior.

```
1. Estructura académica   (niveles y cursos)
2. Profesionales
3. Alumnos y familias     (el paso largo)
4. Consentimientos
5. Matrículas
6. Cuentas de acceso
7. Primera comunicación
```

Los pasos 1 y 2 existen en la API. **La interfaz de alta está pendiente**: este
recorrido se hace por consola o por `tinker` en esta versión. Está anotado en
[09 - API](09-API.md), alcance.

---

## 2. Estructura académica

Un colegio chico tiene dos o tres niveles y pocos cursos. Se crean por consola.

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan tinker
```

```php
use App\Models\Nivel;
use App\Models\Curso;
use App\Services\Tenancy\ContextoTenant;

$colegioId = 'ID-DEL-COLEGIO';

$contexto = new ContextoTenant();

$contexto->ejecutarAislado($colegioId, function () use ($colegioId): void {
    $basica = Nivel::create([
        'tenant_id' => $colegioId,
        'nombre' => 'Básica',
        'orden' => 1,
        'activo' => true,
    ]);

    Nivel::create([
        'tenant_id' => $colegioId,
        'nombre' => 'Media',
        'orden' => 2,
        'activo' => true,
    ]);

    foreach ([1, 2, 3, 4, 5, 6] as $grado) {
        Curso::create([
            'tenant_id' => $colegioId,
            'nivel_id' => $basica->id,
            'nombre' => "{$grado}° Básico A",
            'sigla' => "{$grado}A",
            'orden' => $grado,
            'activo' => true,
        ]);
    });
});
```

> **Todo lo que escribe en tablas con RLS tiene que ir dentro de
> `ejecutarAislado()`.** Fuera de una transacción con contexto, la base
> **aborta la operación**. No es un detalle: es el aislamiento fallando
> cerrado, que es lo que tiene que pasar.

---

## 3. Profesionales y su carga

Un profesional puede tener carga en varios cursos. **De esa tabla
(`curso_profesional`) sale el alcance del docente**, y por eso es el paso que
más se olvida: si se salta, el docente ve cero alumnos y parece un error del
sistema.

```php
use App\Models\Profesional;
use App\Models\Curso;

$contexto->ejecutarAislado($colegioId, function () use ($colegioId): void {
    $profesora = Profesional::create([
        'tenant_id' => $colegioId,
        'nombre' => 'Carmen',
        'apellido' => 'Muñoz',
        'cargo' => 'Jefa de la Unidad Técnico Pedagógica',
        'es_docente' => true,
        'activo' => true,
    ]);

    $tercero = Curso::where('sigla', '3A')->first();

    $tercero->profesionales()->attach($profesora->id, ['tipo' => 'titular']);
});
```

---

## 4. Alumnos, familias y contactos

Este es el paso más largo, y el que conviene hacer con un archivo de
importación en vez de a mano. **La interfaz de alta está pendiente.**

### El orden correcto

```
1. Ficha del alumno (sin familia todavía)
2. Ficha de la familia (apoderado)
3. VÍNCULO entre ambos    ← el paso que da acceso
4. Contactos de emergencia
5. Matrícula
```

**El paso 3 es el que abre el acceso.** Sin vínculo, la familia existe pero no
ve a su hijo, y es muy fácil crearlos todos y olvidarse de vincular.

### Con la API

```bash
curl -X POST http://localhost:8000/api/v1/admin/apoderados \
  -H 'Content-Type: application/json' \
  -H 'Cookie: ...' \
  -b 'XSRF-TOKEN=...' \
  -d '{
    "nombre": "Martina",
    "apellido": "Álvarez",
    "correo": "familia.alvarez@ejemplo.cl",
    "telefono": "+56 9 8123 4567",
    "vinculos": [
      {
        "alumno_id": "ID-DEL-ALUMNO",
        "vinculo": "madre",
        "es_responsable_legal": true
      }
    ]
  }'
```

La familia y su vínculo se crean **en la misma operación**, justamente para que
no quede una familia sin hijos.

### Vínculos que hay que distinguir

| Vínculo | Recibe avisos | Puede firmar | Es contacto de emergencia |
|---|---|---|---|
| `es_responsable_legal: true` | Sí | Sí | Opcional |
| Solo contacto | Sí | No | Sí |

Un tercero (cuidador, representante con poder) recibe avisos pero no firma
documentos. La ficha de apoderado sirve para ambos casos; lo que cambia es
`es_responsable_legal`.

---

## 5. Consentimientos

**No es opcional.** El dato de un menor es sensible desde la Ley 20.720, y el
colegio tiene que poder demostrar que la familia autorizó.

```json
{
  "apoderado_id": "ID-DE-LA-FAMILIA",
  "alumno_id": "ID-DEL-ALUMNO",
  "alcance": "todos",
  "version_documento": "v1-2026",
  "otorgado_por": "Martina Álvarez",
  "fecha_otorgamiento": "2026-03-10",
  "evidencia_ruta": "consentimientos/2026/alvarez-martina.pdf"
}
```

Los tres campos que más se olvidan:

| Campo | Por qué importa |
|---|---|
| `version_documento` | Sin la versión no se puede probar **qué texto** firmó la familia |
| `otorgado_por` | Puede no coincidir con el apoderado titular (un abuelo con permiso notarial) |
| `evidencia_ruta` | Si no hay documento escaneado, se deja vacío **y se registra igual**. Un consentimiento sin documento es preferible a ninguno: deja constancia de que se pidió |

---

## 6. Matrículas

La matrícula es lo que hace que un alumno aparezca en un curso, y por lo tanto
lo que hace que su familia reciba los avisos de ese curso.

```bash
curl -X POST http://localhost:8000/api/v1/matriculas \
  -H 'Content-Type: application/json' \
  -b 'XSRF-TOKEN=...' \
  -d '{
    "alumno_id": "ID-DEL-ALUMNO",
    "curso_id": "ID-DEL-CURSO",
    "anio_escolar": 2026,
    "numero_matricula": "2026-0001"
  }'
```

Si no se manda `numero_matricula`, el servidor propone el siguiente libre del
año.

**Con la matrícula activa, el alumno recibe los avisos del curso.** Por eso el
estado importa: pasar a `retirado` lo saca de los próximos envíos.

---

## 7. Cuentas de acceso

Las cuentas no se crean desde la interfaz en esta versión.

**Quién recibe qué:**

| Persona | Rol | Puede ver |
|---|---|---|
| Dirección | `direccion` | Todo el colegio, incluida conducta |
| Secretaria | `administracion` | Todo el colegio, **sin conducta** |
| Profesores | `docente` | Solo sus cursos |
| communications | `comunicaciones` | **Solo comunicaciones**, sin fichas ni conducta |
| Orientación | `orientacion` | Sus cursos, incluida conducta |
| Familias | `apoderado` | **Solo sus hijos** |

La tabla de arriba está en [03](03-MULTITENANCY-Y-ROLES.md) con su
justificación.

**Para que una familia entre**, su correo tiene que estar en la ficha de
`apoderados` y su cuenta tiene que tener `rol = apoderado` y `apoderado_id`
apuntando a esa ficha. Ver [04](04-INSTALACION.md), sección 7.

---

## 8. La primera comunicación

Y aquí está el corazón del sistema.

```bash
curl -X POST http://localhost:8000/api/v1/comunicaciones \
  -H 'Content-Type: application/json' \
  -b 'XSRF-TOKEN=...' \
  -d '{
    "titulo": "Reunión de apoderados de 3° Básico",
    "cuerpo": "Se extiende la invitación a la reunión del curso. Fecha: jueves 12, 18:30.",
    "canal": "correo",
    "tipo_destinatario": "curso",
    "curso_id": "ID-DEL-CURSO",
    "requiere_acuse": true,
    "enviar": true
  }'
```

La respuesta dice cuántas familias salieron:

```json
{
  "datos": { "referencia": "COM-2026-000001", ... },
  "mensaje": "Comunicación enviada a 18 familia(s)."
}
```

### Si sale "0 familias"

El sistema **no manda en silencio**. Devuelve un error diciendo que no encontró
ninguna familia destinataria.

Eso es a propósito: un aviso que no salió, con la impresión de que sí, es
peor que no mandar el aviso.

### Ver la entrega

```bash
curl http://localhost:8000/api/v1/comunicaciones/ID/recibos \
  -H 'Cookie: ...'
```

```json
{
  "referencia": "COM-2026-000001",
  "total_destinatarios": 18,
  "total_recibos": 11,
  "pendientes": 7,
  "porcentaje_acuse": 61,
  "detalle": [
    { "nombre_completo": "Martina Álvarez", "recibido": true,  "medio": "web" },
    { "nombre_completo": "Sebastián Ríos",  "recibido": false }
  ]
}
```

**Esta pantalla es la razón de existir del sistema.** Cuando la familia
llame preguntando, la respuesta sale de acá.

### Registrar un acuse recibido por otro canal

```bash
curl -X POST http://localhost:8000/api/v1/comunicaciones/ID/recibos \
  -H 'Content-Type: application/json' \
  -b 'XSRF-TOKEN=...' \
  -d '{ "medio": "whatsapp", "apoderado_id": "ID-DE-LA-FAMILIA" }'
```

Solo lo acepta si esa familia **fue destinataria** del aviso. Sin esa
comprobación, se podría "acusar" un aviso dirigido a otro curso y el conteo
de entrega dejaría de significar nada.

---

## 9. Comprobación final

Antes de considerar que el colegio está operando:

```bash
php artisan colegio:verificar-aislamiento
```

Y una comprobación manual que vale la pena hacer **a mano**:

1. Entrar como **apoderado** de una familia.
2. Confirmar que `/alumnos` muestra **solo** a sus hijos.
3. Intentar abrir por URL la ficha de un alumno de otro curso.
   **Debe dar "No encontrado".**
4. Entrar como **comunicaciones**.
5. Confirmar que `/conducta` da "No encontrado".
6. Entrar como **dirección** y confirmar que `/conducta` sí muestra.

Si alguno de esos cuatro resultados no es el esperado, hay que parar. Ver
[10](10-PRUEBAS-Y-ESTADO.md).