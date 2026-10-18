# 02 - Modelo de datos

Este documento explica qué tablas existen y, más importante, **por qué están
separadas como están**.

---

## 1. El mapa

```
colegios  (tenant, SIN RLS)
   │
   ├── niveles ──┐
   │             ├── cursos ──┬── curso_profesional ── profesionales
   │             │            │
   │             │            └── matriculas ── alumnos ──┬── apoderado_alumnos ── apoderados
   │             │                                       │        │
   │             │                                       │        └── contactos_emergencia
   │             │                                       │
   │             │                                       ├── asistencias          ← TABLA PROPIA
   │             │                                       │
   │             │                                       ├── conducta_incidentes  ← TABLA PROPIA
   │             │                                       │
   │             │                                       ├── entrevistas ── actas_reunion
   │             │                                       │
   │             │                                       └── consentimientos
   │             │
   │             └── (vía matriculas)
   │
   ├── comunicaciones ──┬── comunicacion_destinatarios ── apoderados
   │                    └── comunicacion_recibos
   │
   ├── plantillas_comunicacion
   ├── usuarios ── profesionales | apoderados
   ├── bitacora_accesos
   └── contadores_folio
```

Casi todas las tablas llevan `tenant_id`. Las dos excepciones son `colegios`
y `sessions`, y están justificadas en [03](03-MULTITENANCY-Y-ROLES.md).

---

## 2. La decisión central: asistencia y conducta son tablas separadas

**Esto es lo que más conviene entender del modelo de datos.**

### Por qué no están juntas

Porque no son lo mismo, y porque las consequences de juntarlas son
silenciosas:

| | Asistencia | Conducta |
|---|---|---|
| Qué registra | Si el alumno estuvo | Qué hizo el alumno |
| Cuándo | Todos los días | Cuando ocurre algo |
| Contra qué | El calendario | El reglamento interno |
| Quién lo registra | El docente del curso | El docente u orientación |
| Quién lo lee | El docente y **la familia** | El colegio, **nunca la familia** |
| Qué permite | Contar presente/ausente | Registrar medida y gravité |

Si estuvieran en una sola tabla, cada fila diaria tendría una columna
`estado` que a veces significa "faltó" y a veces "se portó mal". El resultado
sería:

- un historial que no se puede consultar sin saber qué significa cada valor;
- **permisos que no se pueden diferenciar**: para proteger la conducta habría
  que proteger la asistencia, o al revés;
- una hoja de asistencia donde "el alumno faltó" y "el alumno tuvo un
  conflicto" se leen igual.

### Cómo queda en el código

Son **dos migraciones distintas**, **dos modelos**, **dos Policies**, **dos
endpoints** y **dos suites de pruebas**:

```
2026_10_17_000014_crear_tabla_asistencias.php
2026_10_17_000015_crear_tabla_conducta_incidentes.php

app/Models/Asistencia.php          app/Models/IncidenteConducta.php
app/Policies/AsistenciaPolicy.php  app/Policies/IncidenteConductaPolicy.php

GET|POST /api/v1/asistencias        GET|POST /api/v1/conducta
```

En la interfaz son dos pestañas separadas (`asistencia.vue` y `conducta.vue`).

### La prueba de que funciona

`api/tests/Feature/Registro/AsistenciaYConductaTest.php` comprueba, entre otras
cosas, que registrar una inasistencia **no** crea un registro de conducta, y
que registrar un incidente **no** toca la asistencia.

---

## 3. La regla de oro de la asistencia

```php
// MAL
if ($registro->estado === null) {
    $ausentes[] = $alumno;   // "no registrado" tratado como "ausente"
}
```

Un `null` en `estado` **no** significa ausente. Significa **"aún no
registrado"**.

Es la diferencia entre "el alumno faltó" y "el colegio todavía no lo sabe", y
son dos hechos que no se pueden inventar uno por el otro. Tratar el vacío
como ausencia produce acusaciones falsas.

### Y una sutileza: `justificado` no es presente

`EstadoAsistencia::cuentaComoPresente()` devuelve `true` para `presente` y
`tarde`, y **`false` para `justificado`**.

Si "justificado" contara como presente, el porcentaje de asistencia de un
alumno con justificativos médicos subiría sin que haya venido nunca.

---

## 4. Las tres tablas del módulo central

El módulo de comunicaciones usa tres tablas, y cada una responde una pregunta
distinta:

| Tabla | Pregunta que responde |
|---|---|
| `comunicaciones` | ¿Qué se dijo, cuándo, por qué canal, y quién lo envió? |
| `comunicacion_destinatarios` | ¿A QUIÉN se le dijo? |
| `comunicacion_recibos` | ¿Quién confirmó que lo recibió? |

### Por qué los destinatarios no son una columna

Porque los destinatarios se **resuelven al enviar**. Un aviso a un curso de
35 alumnos genera 35 filas: cada familia se enteró por su cuenta y puede
confirmar por su cuenta.

Esa lista **queda escrita**. Si en marzo se cambió la matrícula del hijo de
una familia y en junio se manda un aviso al curso, la familia que hay que
avisar es la de marzo. Y si en septiembre preguntan "¿nos avisaron esto?",
la respuesta sale de `comunicacion_destinatarios`, no de recalcular a quién le
correspondía.

`ResolvedorDeDestinatarios` se encarga de esto, y deduplica: una familia con
dos hijos en el mismo curso recibe **un** aviso, no dos.

### Por qué el acuse es único por familia y aviso

`comunicacion_recibos` tiene índice único en
`(tenant_id, comunicacion_id, apoderado_id)`.

Si la familia confirma dos veces, se **actualiza** la fecha en lugar de
duplicar la fila. Dos acuses del mismo aviso no dicen más que uno, y un
conteo de "quién confirmó" que se puede inflar no sirve para nada.

---

## 5. Un aviso enviado no se edita

`EstadoComunicacion` tiene solo dos estados, y `enviada` es terminal:

- `borrador`: se puede editar.
- `enviada`: **no se toca**.

La razón es directa: si se pudiera modificar el texto de un aviso ya
entregado, el registro dejaría de servir para demostrar qué se le dijo a la
familia, que es la razón de existir del módulo.

Si hay que corregir algo, se manda otra comunicación.

**Ojo:** un borrador **no** tiene destinatarios todavía. La lista se resuelve
al enviar, no antes: si el colegio escribe un aviso en marzo y lo envía en
abril, tiene que llegar a las familias de abril.

---

## 6. Otras separaciones que son deliberadas

### `profesionales` y `usuarios` son tablas distintas

Un profesional existe en el colegio aunque no tenga cuenta para entrar (un
docente reemplazado, por ejemplo). Y una cuenta de acceso no siempre es un
profesional: la secretaria de admisiones entra al sistema y no tiene ficha
profesional.

### `contactos_emergencia` y `apoderados` son tablas distintas

Son datos de terceros. Un contacto de emergencia puede ser un vecino que no
tiene ninguna relación administrativa con el colegio. Mezclarlos haría que
el colegio terminara enviando comunicaciones legales a alguien que no tiene
por qué recibirlas.

### `apoderado_alumnos` es una tabla puente con consecuencias

Una familia puede tener dos hijos y un alumno puede tener dos apoderados. Es
la fuente de verdad de "quién puede ver a quién", y de ahí sale el alcance de
cada persona (docs/03).

Tiene además `es_responsable_legal`: un tercero (cuidador, representante)
recibe avisos pero no firma documentos.

### `curso_profesional`: de dónde sale el alcance del docente

El alcance de un docente **no** se deduce del cargo. Sale de los cursos donde
realmente tiene carga. Sin esta tabla, un profe de Matemática vería a todos
los alumnos de todos los cursos donde hay Matemática, que no es lo mismo que
tener el curso a cargo.

---

## 7. Índices que importan

| Tabla | Índice | Por qué |
|---|---|---|
| `asistencias` | único `(tenant_id, alumno_id, fecha)` | Un alumno no puede tener dos estados el mismo día |
| `comunicacion_recibos` | único `(tenant_id, comunicacion_id, apoderado_id)` | Un acuse por familia y aviso |
| `comunicacion_destinatarios` | único `(tenant_id, comunicacion_id, apoderado_id)` | La misma familia no aparece dos veces en un aviso |
| `matriculas` | único `(tenant_id, anio_escolar, numero_matricula)` | La referencia dictada por recepción no se repite |
| `matriculas` | único `(tenant_id, alumno_id, anio_escolar)` | Un alumno no se matricula dos veces el mismo año |
| `contacts_emergencia` | único `(tenant_id, alumno_id, prioridad)` | Las posiciones de llamada son coherentes |

Los índices únicos no son una optimización: son **garantías de negocio**. Si
se eliminan, el sistema empieza a mentir.

---

## 8. Sobre el generador de referencias

`comunicaciones.referencia` sale de `contadores_folio`, con bloqueo de fila
(`SELECT ... FOR UPDATE`) dentro de la transacción que abrió el middleware.

Un `count() + 1` no sirve: dos avisos enviados en el mismo segundo leerían el
mismo contador. La garantía la da PostgreSQL, no la aplicación.

El índice único de `(tenant_id, referencia)` es la red de seguridad por si
alguna vez se llegara a una carrera.