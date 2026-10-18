<script setup lang="ts">
/**
 * Agenda de entrevistas y reuniones.
 *
 * La agenda es lo que convierte "el colegio nunca nos avisó" en "el colegio
 * agendó esto el martes y aquí está el motivo". Por eso cada entrevista
 * guarda el motivo y no solo la hora.
 */
import type { Entrevista, Paginado } from '~/tipos/api';

definePageMeta({ middleware: 'auth' });

const cargando = ref(true);
const error = ref<string | null>(null);
const entrevistas = ref<Entrevista[]>([]);

onMounted(async () => {
  try {
    const respuesta = await useRequestFetch()<Paginado<Entrevista>>('/api/v1/entrevistas?per_page=30');
    entrevistas.value = respuesta.data;
  } catch {
    error.value = 'No se pudo cargar la agenda.';
  } finally {
    cargando.value = false;
  }
});

function fechaLegible(valor: string): string {
  return new Date(`${valor}T12:00:00`).toLocaleDateString('es-CL', {
    weekday: 'short',
    day: '2-digit',
    month: 'long',
  });
}
</script>

<template>
  <div>
    <h1>Agenda</h1>

    <p class="lema">
      Entrevistas y reuniones con las familias.
    </p>

    <p v-if="cargando">Cargando…</p>

    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <p v-else-if="entrevistas.length === 0" class="vacio">
      No hay entrevistas agendadas.
    </p>

    <ul v-else class="lista">
      <li v-for="entrevista in entrevistas" :key="entrevista.id" class="tarjeta">
        <div class="fila">
          <div>
            <span class="fecha">{{ fechaLegible(entrevista.fecha) }} · {{ entrevista.hora }}</span>
            <h2>{{ entrevista.alumno?.nombre_completo ?? 'Reunión de curso' }}</h2>
          </div>

          <span class="estado" :class="entrevista.estado">{{ entrevista.estado_etiqueta }}</span>
        </div>

        <p class="motivo">{{ entrevista.motivo }}</p>

        <p class="detalle">
          <template v-if="entrevista.apoderado">Con {{ entrevista.apoderado.nombre_completo }} · </template>
          <template v-if="entrevista.profesional">{{ entrevista.profesional.cargo }} · </template>
          <template v-if="entrevista.lugar">{{ entrevista.lugar }}</template>
        </p>

        <p v-if="entrevista.tiene_acta" class="acta">Con acta registrada.</p>
      </li>
    </ul>
  </div>
</template>

<style scoped>
h1 {
  margin: 0 0 0.3rem;
  font-size: 1.35rem;
  color: #1f2933;
}

.lema {
  margin: 0 0 1.2rem;
  color: #52606d;
  font-size: 0.9rem;
}

.lista {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.8rem;
}

.tarjeta {
  background: #fff;
  border: 1px solid #dfe4e8;
  border-radius: 8px;
  padding: 1rem 1.1rem;
}

.fila {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.fecha {
  font-size: 0.78rem;
  color: #7b8794;
}

h2 {
  margin: 0.1rem 0 0;
  font-size: 1.02rem;
  color: #1f2933;
}

.estado {
  font-size: 0.78rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: #eef2f4;
  color: #52606d;
}

.estado.realizada {
  background: #e3f2e9;
  color: #1f6b45;
}

.estado.cancelada {
  background: #fdeaea;
  color: #8a2020;
}

.motivo {
  margin: 0.6rem 0 0.3rem;
  font-size: 0.92rem;
  color: #3e4c59;
}

.detalle {
  margin: 0;
  font-size: 0.82rem;
  color: #7b8794;
}

.acta {
  margin: 0.5rem 0 0;
  font-size: 0.84rem;
  color: #1f6b45;
}

.vacio {
  color: #7b8794;
  font-size: 0.9rem;
}

.error {
  background: #fdeaea;
  border: 1px solid #f0b4b4;
  color: #8a2020;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
}
</style>