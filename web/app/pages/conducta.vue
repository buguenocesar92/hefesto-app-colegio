<script setup lang="ts">
/**
 * Conducta: incidentes registrados.
 *
 * PANTALLA RESTRINGIDA
 * -------------------
 * Esta pantalla solo existe para los roles con permiso de lectura de
 * conducta. Si alguien sin permiso entra por URL, la API responde 404 y acá
 * se muestra un mensaje neutro.
 *
 * La separación con asistencia es visible en la interfaz a propósito: son
 * dos pestañas distintas, con datos distintos y permisos distintos. Un
 * maestro de comunicaciones no ve esta pestaña porque no puede leer lo que
 * hay en ella, y esa es la forma más simple de que la regla no dependa de
 * que alguien se acuerde.
 */
import type { Incidente, Paginado } from '~/tipos/api';

definePageMeta({ middleware: 'auth' });

const { capacidades } = useSesion();

const cargando = ref(true);
const noAutorizado = ref(false);
const incidentes = ref<Incidente[]>([]);

onMounted(async () => {
  try {
    const respuesta = await useRequestFetch()<Paginado<Incidente>>('/api/v1/conducta?per_page=30');
    incidentes.value = respuesta.data;
  } catch {
    noAutorizado.value = true;
  } finally {
    cargando.value = false;
  }
});

function fechaLegible(valor: string): string {
  return new Date(valor).toLocaleDateString('es-CL', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}
</script>

<template>
  <div>
    <h1>Conducta</h1>

    <p class="lema">
      Incidentes registrados por el colegio. Esta información es sensible: se
      comunica a la familia por el aviso formal, no por el registro interno.
    </p>

    <p v-if="!capacidades?.ve_conducta" class="aviso">
      Su rol no tiene acceso al registro de conducta.
    </p>

    <p v-if="cargando">Cargando…</p>

    <div v-else-if="noAutorizado" class="vacio">
      <p>No encontrado.</p>
    </div>

    <p v-else-if="incidentes.length === 0" class="vacio">
      No hay incidentes registrados.
    </p>

    <ul v-else class="lista">
      <li v-for="incidente in incidentes" :key="incidente.id" class="tarjeta">
        <div class="fila">
          <div>
            <span class="fecha">{{ fechaLegible(incidente.fecha) }}</span>
            <h2>{{ incidente.alumno?.nombre_completo ?? 'Alumno' }}</h2>
          </div>

          <span class="gravedad" :class="incidente.gravedad">{{ incidente.gravedad_etiqueta }}</span>
        </div>

        <p class="categoria">{{ incidente.categoria_etiqueta }} · {{ incidente.curso?.sigla }}</p>
        <p class="descripcion">{{ incidente.descripcion }}</p>

        <p v-if="incidente.medida" class="medida">
          <strong>Medida:</strong> {{ incidente.medida }}
        </p>

        <p v-if="incidente.exige_comunicacion && !incidente.notificado_apoderado" class="pendiente">
          Esta gravedad obliga a avisar a la familia. Falta la comunicación.
        </p>
        <p v-else-if="incidente.notificado_apoderado" class="notificado">
          Familia avisada.
        </p>
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
  max-width: 46rem;
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
  font-size: 0.76rem;
  color: #7b8794;
}

h2 {
  margin: 0.1rem 0 0;
  font-size: 1.02rem;
  color: #1f2933;
}

.gravedad {
  font-size: 0.78rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
}

.gravedad.leve {
  background: #eef2f4;
  color: #52606d;
}

.gravedad.media {
  background: #fdf3e2;
  color: #8a5a10;
}

.gravedad.grave {
  background: #fdeaea;
  color: #8a2020;
}

.categoria {
  margin: 0.5rem 0 0.3rem;
  font-size: 0.8rem;
  color: #7b8794;
}

.descripcion {
  margin: 0;
  font-size: 0.92rem;
  color: #3e4c59;
}

.medida {
  margin: 0.5rem 0 0;
  font-size: 0.88rem;
  color: #52606d;
}

.pendiente {
  margin: 0.6rem 0 0;
  font-size: 0.85rem;
  color: #8a5a10;
}

.notificado {
  margin: 0.6rem 0 0;
  font-size: 0.85rem;
  color: #1f6b45;
}

.aviso {
  background: #fdf3e2;
  border: 1px solid #f0dcb4;
  color: #8a5a10;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
  font-size: 0.88rem;
}

.vacio {
  color: #7b8794;
  font-size: 0.9rem;
}
</style>