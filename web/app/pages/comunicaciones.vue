<script setup lang="ts">
/**
 * COMUNICACIONES: el módulo central.
 *
 * Esta pantalla responde a la pregunta que el colegio no tiene forma de
 * responder hoy: "¿qué le avisamos a esta familia, y lo recibió?".
 *
 * Por eso la columna de acuse está al lado del aviso y no escondida en un
 * detalle: el número de "3 de 5 confirmaron" es el dato que la secretaría
 * necesita para volver a llamar a quien falta.
 */
import type { Comunicacion, Paginado } from '~/tipos/api';

definePageMeta({ middleware: 'auth' });

const { usuario, capacidades } = useSesion();

const pendientes = ref(false);
const cargando = ref(true);
const error = ref<string | null>(null);

const comunicaciones = ref<Comunicacion[]>([]);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await useRequestFetch()<Paginado<Comunicacion>>(
      `/api/v1/comunicaciones?per_page=30${pendientes.value ? '&pendientes=1' : ''}`,
    );

    comunicaciones.value = respuesta.data;
  } catch {
    error.value = 'No se pudo cargar la lista de comunicaciones.';
  } finally {
    cargando.value = false;
  }
}

await cargar();
watch(pendientes, cargar);

function fechaLegible(valor: string | null): string {
  if (!valor) {
    return 'Sin enviar';
  }

  return new Date(valor).toLocaleDateString('es-CL', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function claseAcuse(comunicacion: Comunicacion): string {
  if (!comunicacion.requiere_acuse) {
    return 'sin-acuse';
  }

  const porcentaje = comunicacion.entrega?.porcentaje_acuse ?? 0;

  if (porcentaje === 100) {
    return 'completo';
  }

  return porcentaje === 0 ? 'pendiente' : 'parcial';
}
</script>

<template>
  <div>
    <header class="titulo">
      <div>
        <h1>Comunicaciones</h1>
        <p class="lema">
          Registro de lo que el colegio envió a las familias, con acuse de
          recibo cuando corresponde.
        </p>
      </div>

      <label v-if="capacidades?.envia_comunicaciones" class="filtro">
        <input v-model="pendientes" type="checkbox" />
        Solo las que esperan acuse
      </label>
    </header>

    <p v-if="cargando">Cargando…</p>

    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <p v-else-if="comunicaciones.length === 0" class="vacio">
      {{ pendientes ? 'No hay comunicaciones esperando acuse.' : 'Todavía no hay comunicaciones registradas.' }}
    </p>

    <ul v-else class="lista">
      <li v-for="comunicacion in comunicaciones" :key="comunicacion.id" class="tarjeta">
        <div class="fila-superior">
          <div>
            <span class="referencia">{{ comunicacion.referencia }}</span>
            <h2>{{ comunicacion.titulo }}</h2>
          </div>

          <span v-if="comunicacion.requiere_acuse" class="acuse" :class="claseAcuse(comunicacion)">
            <template v-if="comunicacion.entrega">
              {{ comunicacion.entrega.total_recibos }} de
              {{ comunicacion.entrega.total_destinatarios }} confirmaron
            </template>
            <template v-else>
              Requiere acuse
            </template>
          </span>
        </div>

        <dl class="detalle">
          <div>
            <dt>Canal</dt>
            <dd>{{ comunicacion.canal_etiqueta }}</dd>
          </div>
          <div>
            <dt>Destinatarios</dt>
            <dd>{{ comunicacion.tipo_destinatario_etiqueta }}</dd>
          </div>
          <div>
            <dt>Enviada</dt>
            <dd>{{ fechaLegible(comunicacion.enviada_en) }}</dd>
          </div>
          <div>
            <dt>Estado</dt>
            <dd>{{ comunicacion.estado_etiqueta }}</dd>
          </div>
        </dl>

        <p class="cuerpo">{{ comunicacion.cuerpo }}</p>

        <p v-if="comunicacion.entrega && comunicacion.entrega.pendientes > 0" class="pendientes">
          Falta confirmar recepción en
          {{ comunicacion.entrega.pendientes }}
          {{ comunicacion.entrega.pendientes === 1 ? 'familia' : 'familias' }}.
        </p>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.titulo {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.2rem;
}

h1 {
  margin: 0 0 0.3rem;
  font-size: 1.35rem;
  color: #1f2933;
}

.lema {
  margin: 0;
  color: #52606d;
  font-size: 0.9rem;
}

.filtro {
  font-size: 0.88rem;
  color: #3e4c59;
  display: flex;
  align-items: center;
  gap: 0.4rem;
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

.fila-superior {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.referencia {
  font-size: 0.76rem;
  color: #7b8794;
  font-family: ui-monospace, monospace;
}

h2 {
  margin: 0.1rem 0 0;
  font-size: 1.05rem;
  color: #1f2933;
}

.acuse {
  font-size: 0.78rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  white-space: nowrap;
}

.acuse.completo {
  background: #e3f2e9;
  color: #1f6b45;
}

.acuse.parcial {
  background: #fdf3e2;
  color: #8a5a10;
}

.acuse.pendiente {
  background: #fdeaea;
  color: #8a2020;
}

.acuse.sin-acuse {
  background: #eef2f4;
  color: #52606d;
}

.detalle {
  display: flex;
  gap: 1.4rem;
  margin: 0.7rem 0;
  flex-wrap: wrap;
}

.detalle dt {
  font-size: 0.72rem;
  color: #7b8794;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detalle dd {
  margin: 0.1rem 0 0;
  font-size: 0.9rem;
  color: #3e4c59;
}

.cuerpo {
  white-space: pre-line;
  font-size: 0.92rem;
  color: #3e4c59;
  margin: 0;
}

.pendientes {
  margin: 0.7rem 0 0;
  font-size: 0.85rem;
  color: #8a5a10;
}

.vacio {
  color: #7b8794;
  font-size: 0.92rem;
}

.error {
  background: #fdeaea;
  border: 1px solid #f0b4b4;
  color: #8a2020;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
}
</style>