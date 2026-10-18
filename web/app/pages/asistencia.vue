<script setup lang="ts">
/**
 * Asistencia: hoja del día por curso.
 *
 * LA REGLA DE LA PANTALLA
 * ----------------------
 * "No registrado" NO es lo mismo que "Ausente". La celda vacía significa que
 * el colegio todavía no sabe; la celda en rojo significa que sabe que no
 * vino. Mezclarlas haría que un alumno con la hoja a medio llenar pareciera
 * ausente, y esa es una acusación que no se puede hacer.
 */
import type { Asistencia, Curso } from '~/tipos/api';

definePageMeta({ middleware: 'auth' });

const { capacidades } = useSesion();

const fecha = ref(new Date().toISOString().slice(0, 10));
const cursos = ref<Curso[]>([]);
const cursoId = ref<string>('');
const registros = ref<Asistencia[]>([]);
const cargando = ref(false);
const aviso = ref<string | null>(null);
const error = ref<string | null>(null);

const estados = [
  { valor: 'presente', etiqueta: 'Presente' },
  { valor: 'tarde', etiqueta: 'Llegó tarde' },
  { valor: 'ausente', etiqueta: 'Ausente' },
  { valor: 'justificado', etiqueta: 'Justificado' },
];

const puedeRegistrar = computed(() => capacidades.value?.registra_asistencia === true);

async function cargarCursos(): Promise<void> {
  try {
    const respuesta = await useRequestFetch()<{ data: Curso[] }>('/api/v1/admin/cursos');
    cursos.value = respuesta.data;

    if (!cursoId.value && cursos.value.length > 0) {
      cursoId.value = cursos.value[0]!.id;
    }
  } catch {
    // Un apoderado no puede leer el catálogo de cursos: no es un error, es
    // que no tiene ese permiso.
    cursos.value = [];
  }
}

async function cargarRegistro(): Promise<void> {
  if (!cursoId.value) {
    registros.value = [];

    return;
  }

  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await useRequestFetch()<{ data: Asistencia[] }>(
      `/api/v1/asistencias?curso_id=${cursoId.value}&fecha=${fecha.value}`,
    );

    registros.value = respuesta.data;
  } catch {
    error.value = 'No se pudo cargar la hoja de asistencia.';
  } finally {
    cargando.value = false;
  }
}

async function guardar(): Promise<void> {
  aviso.value = null;
  error.value = null;

  const aEnviar = registros.value
    .filter((registro) => registro.estado)
    .map((registro) => ({
      alumno_id: registro.alumno_id,
      estado: registro.estado,
      justificacion: registro.justificacion ?? null,
    }));

  if (aEnviar.length === 0) {
    aviso.value = 'No hay nada registrado todavía.';

    return;
  }

  try {
    const respuesta = await $fetch<{ mensaje: string }>('/api/v1/asistencias', {
      method: 'POST',
      body: {
        fecha: fecha.value,
        curso_id: cursoId.value,
        registros: aEnviar,
      },
    });

    aviso.value = respuesta.mensaje;
  } catch {
    error.value = 'No se pudo guardar la asistencia.';
  }
}

await cargarCursos();
await cargarRegistro();
watch([cursoId, fecha], cargarRegistro);
</script>

<template>
  <div>
    <h1>Asistencia</h1>

    <p v-if="!puedeRegistrar" class="aviso">
      Su rol puede ver la asistencia, pero no registrarla. Quien marca presencia
      es el docente a cargo del curso.
    </p>

    <div class="controles">
      <label>
        Fecha
        <input v-model="fecha" type="date" />
      </label>

      <label v-if="cursos.length">
        Curso
        <select v-model="cursoId">
          <option v-for="curso in cursos" :key="curso.id" :value="curso.id">
            {{ curso.sigla }} — {{ curso.nombre }}
          </option>
        </select>
      </label>
    </div>

    <p v-if="cargando">Cargando…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <table v-else class="tabla">
      <thead>
        <tr>
          <th>Alumno</th>
          <th>Estado</th>
          <th>Justificación</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="registro in registros" :key="registro.id || registro.alumno_id">
          <td>{{ registro.alumno?.nombre_completo ?? registro.alumno_id }}</td>
          <td>
            <select v-model="registro.estado" :disabled="!puedeRegistrar">
              <option value="">Sin registrar</option>
              <option v-for="estado in estados" :key="estado.valor" :value="estado.valor">
                {{ estado.etiqueta }}
              </option>
            </select>
          </td>
          <td>
            <input
              v-model="registro.justificacion"
              type="text"
              placeholder="Solo si está justificado"
              :disabled="!puedeRegistrar || registro.estado !== 'justificado'"
            />
          </td>
        </tr>
      </tbody>
    </table>

    <p v-if="registros.length === 0 && !cargando" class="vacio">
      No hay alumnos registrados para ese curso y fecha.
    </p>

    <p v-if="aviso" class="aviso">{{ aviso }}</p>

    <button v-if="puedeRegistrar" type="button" class="guardar" @click="guardar">
      Guardar asistencia
    </button>
  </div>
</template>

<style scoped>
h1 {
  margin: 0 0 1rem;
  font-size: 1.35rem;
  color: #1f2933;
}

.controles {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.85rem;
  color: #52606d;
}

select,
input {
  padding: 0.4rem 0.6rem;
  border: 1px solid #cbd2d9;
  border-radius: 6px;
  font-size: 0.9rem;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid #dfe4e8;
  border-radius: 8px;
  overflow: hidden;
}

th,
td {
  text-align: left;
  padding: 0.5rem 0.8rem;
  font-size: 0.9rem;
  border-bottom: 1px solid #eef2f4;
}

th {
  background: #f8f9fa;
  font-size: 0.78rem;
  text-transform: uppercase;
  color: #52606d;
}

.guardar {
  margin-top: 1rem;
  padding: 0.6rem 1.2rem;
  border: 0;
  border-radius: 6px;
  background: #2f6f4f;
  color: #fff;
  cursor: pointer;
  font-size: 0.92rem;
}

.aviso {
  background: #fdf3e2;
  border: 1px solid #f0dcb4;
  color: #8a5a10;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
  font-size: 0.88rem;
}

.error {
  background: #fdeaea;
  border: 1px solid #f0b4b4;
  color: #8a2020;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
}

.vacio {
  color: #7b8794;
  font-size: 0.9rem;
}
</style>