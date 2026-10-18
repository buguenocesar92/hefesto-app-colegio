<script setup lang="ts">
/**
 * Ficha del alumno.
 *
 * Esta pantalla muestra datos sensibles: nombre completo, fecha de
 * nacimiento, contactos de emergencia y apoderados. Por eso la API exige
 * `verFichaCompleta`, que es un permiso más restrictivo que "ver que el
 * alumno existe".
 *
 * Si la API responde 404, se muestra un mensaje neutro. No se distingue
 * entre "el alumno no existe" y "no tiene permiso": esa diferencia sería
 * una forma de confirmar que alguien existe en algún lado.
 */
import type { Alumno } from '~/tipos/api';

definePageMeta({ middleware: 'auth' });

const ruta = useRoute();

const alumno = ref<Alumno | null>(null);
const cargando = ref(true);
const noEncontrado = ref(false);

onMounted(async () => {
  try {
    const respuesta = await $fetch<Alumno>(`/api/v1/alumnos/${ruta.params.id}`);
    alumno.value = respuesta;
  } catch {
    noEncontrado.value = true;
  } finally {
    cargando.value = false;
  }
});

function fechaLegible(valor: string | null): string {
  if (!valor) {
    return '—';
  }

  return new Date(valor).toLocaleDateString('es-CL', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}
</script>

<template>
  <div>
    <p v-if="cargando">Cargando…</p>

    <div v-else-if="noEncontrado" class="vacio">
      <h1>No encontrado</h1>
      <p>El alumno solicitado no existe o no tiene acceso a él.</p>
      <NuxtLink to="/alumnos">Volver al listado</NuxtLink>
    </div>

    <div v-else-if="alumno">
      <header class="encabezado">
        <div>
          <h1>{{ alumno.nombre_completo }}</h1>
          <p class="subtitulo">
            {{ alumno.edad }} años
            <template v-if="alumno.rut"> · RUT {{ alumno.rut }}</template>
          </p>
        </div>

        <span v-if="alumno.matricula" class="matricula">
          {{ alumno.matricula.numero_matricula }} · {{ alumno.matricula.estado_etiqueta }}
        </span>
      </header>

      <section class="bloque">
        <h2>Curso</h2>
        <p v-if="alumno.matricula?.curso">
          {{ alumno.matricula.curso.nombre }}
        </p>
        <p v-else class="vacio">Sin matrícula vigente.</p>
      </section>

      <section class="bloque">
        <h2>Apoderados</h2>
        <ul v-if="alumno.apoderados && alumno.apoderados.length" class="lista">
          <li v-for="apoderado in alumno.apoderados" :key="apoderado.id">
            {{ apoderado.nombre_completo }}
            <span class="vinculo">{{ apoderado.vinculo }}</span>
            <span v-if="apoderado.es_responsable_legal" class="responsable">Responsable legal</span>
          </li>
        </ul>
        <p v-else class="vacio">Sin apoderados registrados.</p>
      </section>

      <section class="bloque">
        <h2>Contactos de emergencia</h2>
        <ul v-if="alumno.contactos_emergencia && alumno.contactos_emergencia.length" class="lista">
          <li v-for="contacto in alumno.contactos_emergencia" :key="contacto.id">
            {{ contacto.nombre }} ({{ contacto.parentesco }})
            <span class="mono">{{ contacto.telefono }}</span>
            <span class="orden">{{ contacto.prioridad }}º en llamar</span>
          </li>
        </ul>
        <p v-else class="vacio">Sin contactos de emergencia registrados.</p>
      </section>

      <section class="bloque">
        <h2>Datos de contacto</h2>
        <dl class="datos">
          <dt>Dirección</dt>
          <dd>{{ alumno.direccion ?? '—' }}</dd>
          <dt>Teléfono</dt>
          <dd>{{ alumno.telefono ?? '—' }}</dd>
          <dt>Correo</dt>
          <dd>{{ alumno.correo ?? '—' }}</dd>
          <dt>Ingreso</dt>
          <dd>{{ fechaLegible(alumno.fecha_ingreso) }}</dd>
        </dl>
      </section>
    </div>
  </div>
</template>

<style scoped>
.encabezado {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.2rem;
}

h1 {
  margin: 0;
  font-size: 1.35rem;
  color: #1f2933;
}

.subtitulo {
  margin: 0.2rem 0 0;
  color: #52606d;
  font-size: 0.9rem;
}

.matricula {
  font-size: 0.8rem;
  background: #e3f2e9;
  color: #1f6b45;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  font-family: ui-monospace, monospace;
}

.bloque {
  background: #fff;
  border: 1px solid #dfe4e8;
  border-radius: 8px;
  padding: 1rem 1.1rem;
  margin-bottom: 0.8rem;
}

h2 {
  margin: 0 0 0.6rem;
  font-size: 0.95rem;
  color: #1f2933;
}

.lista {
  list-style: none;
  padding: 0;
  margin: 0;
}

.lista li {
  padding: 0.35rem 0;
  border-bottom: 1px solid #f4f6f8;
  font-size: 0.9rem;
}

.lista li:last-child {
  border-bottom: 0;
}

.vinculo {
  color: #7b8794;
  font-size: 0.82rem;
  margin-left: 0.4rem;
}

.responsable {
  background: #eef2f4;
  color: #52606d;
  font-size: 0.74rem;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  margin-left: 0.4rem;
}

.orden {
  color: #7b8794;
  font-size: 0.78rem;
  margin-left: 0.4rem;
}

.mono {
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  margin-left: 0.4rem;
}

.datos {
  display: grid;
  grid-template-columns: 8rem 1fr;
  gap: 0.3rem 1rem;
  margin: 0;
  font-size: 0.9rem;
}

.datos dt {
  color: #7b8794;
}

.datos dd {
  margin: 0;
  color: #3e4c59;
}

.vacio {
  color: #7b8794;
  font-size: 0.9rem;
}
</style>