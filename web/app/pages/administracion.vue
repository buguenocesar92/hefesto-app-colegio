<script setup lang="ts">
/**
 * Administración: catálogos del colegio.
 *
 * Esta pantalla es solo lectura en este primer corte. Crear niveles, cursos,
 * profesionales y plantillas existe como API (ver routes/web.php), pero la
 * interfaz para hacerlo quedó fuera: es mejor dejar el botón ausente que un
 * formulario a medio hacer.
 *
 * `pendiente` en cada bloque lo dice así mismo.
 */
import type { Apoderado, Curso } from '~/tipos/api';

definePageMeta({ middleware: 'auth' });

const { capacidades } = useSesion();

const cursos = ref<Curso[]>([]);
const apoderados = ref<Apoderado[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  if (!capacidades.value?.administra) {
    await navigateTo('/');

    return;
  }

  try {
    const [cursosRespuesta, apoderadosRespuesta] = await Promise.all([
      useRequestFetch()<{ data: Curso[] }>('/api/v1/admin/cursos'),
      useRequestFetch()<{ data: Apoderado[] }>('/api/v1/admin/apoderados?per_page=50'),
    ]);

    cursos.value = cursosRespuesta.data;
    apoderados.value = apoderadosRespuesta.data;
  } catch {
    error.value = 'No se pudo cargar la administración.';
  } finally {
    cargando.value = false;
  }
});
</script>

<template>
  <div>
    <h1>Administración</h1>

    <p v-if="cargando">Cargando…</p>

    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <template v-else>
      <section class="bloque">
        <h2>Cursos</h2>

        <table class="tabla">
          <thead>
            <tr>
              <th>Sigla</th>
              <th>Curso</th>
              <th>Nivel</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="curso in cursos" :key="curso.id">
              <td class="mono">{{ curso.sigla }}</td>
              <td>{{ curso.nombre }}</td>
              <td>{{ curso.nivel?.nombre ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="bloque">
        <h2>Familias</h2>

        <table class="tabla">
          <thead>
            <tr>
              <th>Apoderado</th>
              <th>Correo</th>
              <th>Teléfono</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="apoderado in apoderados" :key="apoderado.id">
              <td>{{ apoderado.nombre_completo }}</td>
              <td>{{ apoderado.correo }}</td>
              <td>{{ apoderado.telefono ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <p class="pendiente">
        Pendiente: alta de niveles, cursos, profesionales, plantillas,
        familias y registro de consentimientos. La API de esos operaciones ya
        existe.
      </p>
    </template>
  </div>
</template>

<style scoped>
h1 {
  margin: 0 0 1.2rem;
  font-size: 1.35rem;
  color: #1f2933;
}

.bloque {
  background: #fff;
  border: 1px solid #dfe4e8;
  border-radius: 8px;
  padding: 1rem 1.1rem;
  margin-bottom: 1rem;
}

h2 {
  margin: 0 0 0.7rem;
  font-size: 1rem;
  color: #1f2933;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: 0.45rem 0.6rem;
  font-size: 0.9rem;
  border-bottom: 1px solid #eef2f4;
}

th {
  font-size: 0.76rem;
  text-transform: uppercase;
  color: #52606d;
}

.mono {
  font-family: ui-monospace, monospace;
}

.pendiente {
  background: #fdf3e2;
  border: 1px solid #f0dcb4;
  color: #8a5a10;
  border-radius: 6px;
  padding: 0.7rem 0.9rem;
  font-size: 0.88rem;
}

.error {
  background: #fdeaea;
  border: 1px solid #f0b4b4;
  color: #8a2020;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
}
</style>