<script setup lang="ts">
/**
 * Alumnos.
 *
 * Lo que llega a esta pantalla ya viene filtrado por el alcance de la
 * persona: la API no devuelve los alumnos que esa persona no puede ver, ni
 * siquiera para probar.
 *
 * Para un apoderado, esta pantalla muestra a sus hijos y nada más. El texto
 * de abajo es distinto en ese caso, porque el nombre de la sección cambia
 * según quién la esté mirando.
 */
import type { Alumno, Paginado } from '~/tipos/api';

definePageMeta({ middleware: 'auth' });

const { esApoderado } = useSesion();

const busqueda = ref('');
const cargando = ref(true);
const error = ref<string | null>(null);

const alumnos = ref<Alumno[]>([]);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const consulta = busqueda.value.trim()
      ? `&q=${encodeURIComponent(busqueda.value.trim())}`
      : '';

    const respuesta = await useRequestFetch()<Paginado<Alumno>>('/api/v1/alumnos?per_page=50' + consulta);
    alumnos.value = respuesta.data;
  } catch {
    error.value = 'No se pudo cargar la lista de alumnos.';
  } finally {
    cargando.value = false;
  }
}

await cargar();
watch(busqueda, cargar);
</script>

<template>
  <div>
    <header class="titulo">
      <h1>{{ esApoderado ? 'Mis hijos' : 'Alumnos' }}</h1>

      <input
        v-model="busqueda"
        class="busqueda"
        type="search"
        placeholder="Buscar por nombre o RUT"
      />
    </header>

    <p v-if="cargando">Cargando…</p>

    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <p v-else-if="alumnos.length === 0" class="vacio">
      {{ esApoderado ? 'No hay alumnos asociados a su cuenta.' : 'No hay alumnos que coincidan con la búsqueda.' }}
    </p>

    <table v-else class="tabla">
      <thead>
        <tr>
          <th>Alumno</th>
          <th>Curso</th>
          <th>Matrícula</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="alumno in alumnos" :key="alumno.id">
          <td>
            <NuxtLink :to="`/alumnos/${alumno.id}`" class="enlace-alumno">
              {{ alumno.nombre_completo }}
            </NuxtLink>
            <span class="detalle">{{ alumno.edad }} años</span>
          </td>
          <td>{{ alumno.matricula?.curso?.sigla ?? '—' }}</td>
          <td class="mono">{{ alumno.matricula?.numero_matricula ?? '—' }}</td>
          <td>
            <span class="etiqueta">{{ alumno.matricula?.estado_etiqueta ?? 'Sin matrícula' }}</span>
          </td>
        </tr>
      </tbody>
    </table>

    <p class="nota">
      La lista muestra solo los alumnos a los que su rol tiene acceso. Si
      espera ver a alguien y no aparece, hable con la secretaría del colegio.
    </p>
  </div>
</template>

<style scoped>
.titulo {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.2rem;
  flex-wrap: wrap;
}

h1 {
  margin: 0;
  font-size: 1.35rem;
  color: #1f2933;
}

.busqueda {
  padding: 0.45rem 0.7rem;
  border: 1px solid #cbd2d9;
  border-radius: 6px;
  min-width: 16rem;
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
  padding: 0.6rem 0.8rem;
  font-size: 0.9rem;
  border-bottom: 1px solid #eef2f4;
}

th {
  background: #f8f9fa;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #52606d;
}

.enlace-alumno {
  color: #2f6f4f;
  text-decoration: none;
  font-weight: 600;
}

.detalle {
  display: block;
  font-size: 0.78rem;
  color: #7b8794;
}

.mono {
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
}

.etiqueta {
  font-size: 0.8rem;
  color: #3e4c59;
}

.vacio,
.nota {
  color: #7b8794;
  font-size: 0.9rem;
}

.nota {
  margin-top: 1rem;
}

.error {
  background: #fdeaea;
  border: 1px solid #f0b4b4;
  color: #8a2020;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
}
</style>