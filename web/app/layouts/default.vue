<script setup lang="ts">
/**
 * Marco general: encabezado, navegación y pie.
 *
 * LA NAVEGACIÓN MUESTRA LO QUE LA SESIÓN DICE QUE PUEDE HACER
 * -----------------------------------------------------------
 * Los menús se arma con `capacidades`, que viene de la API. Ocultar un botón
 * NO es seguridad: si la API exige el permiso, responde 404 aunque el botón
 * estuviera visible. Lo que sí evita es presentar a la persona opciones que
 * no le van a servir.
 */
const { usuario, capacidades, nombreColegio, esApoderado, esDelColegio, salir } = useSesion();

const ruta = useRoute();

const enlaces = computed(() => {
  const actuales: Array<{ to: string; texto: string }> = [];

  if (esApoderado.value) {
    // La familia ve lo suyo, no el tablero completo.
    return [{ to: '/alumnos', texto: 'Mis hijos' }, { to: '/comunicaciones', texto: 'Avisos' }];
  }

  actuales.push({ to: '/', texto: 'Inicio' });
  actuales.push({ to: '/alumnos', texto: 'Alumnos' });

  if (capacidades.value?.envia_comunicaciones) {
    actuales.push({ to: '/comunicaciones', texto: 'Comunicaciones' });
  }

  actuales.push({ to: '/asistencia', texto: 'Asistencia' });

  // La conducta se muestra solo a quien tiene permiso para leerla. Ocultar la
  // pestaña evita la pregunta incómoda de "¿por qué no veo esto?".
  if (capacidades.value?.ve_conducta) {
    actuales.push({ to: '/conducta', texto: 'Conducta' });
  }

  actuales.push({ to: '/agenda', texto: 'Agenda' });

  if (capacidades.value?.administra) {
    actuales.push({ to: '/administracion', texto: 'Administración' });
  }

  return actuales;
});

const activo = (destino: string): boolean =>
  ruta.path === destino || (destino !== '/' && ruta.path.startsWith(`${destino}/`));
</script>

<template>
  <div class="marco">
    <header class="encabezado">
      <div class="identidad">
        <strong>{{ nombreColegio || 'Colegio' }}</strong>
        <span v-if="usuario" class="rol">{{ usuario.rol_etiqueta }}</span>
      </div>

      <nav v-if="esDelColegio || esApoderado" class="navegacion">
        <NuxtLink
          v-for="enlace in enlaces"
          :key="enlace.to"
          :to="enlace.to"
          class="enlace"
          :class="{ activo: activo(enlace.to) }"
        >
          {{ enlace.texto }}
        </NuxtLink>
      </nav>

      <div class="sesion">
        <span v-if="usuario" class="nombre">{{ usuario.nombre_completo }}</span>
        <button v-if="usuario" type="button" class="salir" @click="salir">
          Salir
        </button>
      </div>
    </header>

    <main class="contenido">
      <slot />
    </main>

    <footer class="pie">
      <p>
        Sistema en desarrollo. Contiene datos personales de menores: no debe
        usarse con alumnos reales.
      </p>
    </footer>
  </div>
</template>

<style scoped>
.marco {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f4f6f8;
}

.encabezado {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 0.8rem 1.5rem;
  background: #fff;
  border-bottom: 1px solid #dfe4e8;
  flex-wrap: wrap;
}

.identidad {
  display: flex;
  flex-direction: column;
}

.rol {
  font-size: 0.78rem;
  color: #7b8794;
}

.navegacion {
  display: flex;
  gap: 0.3rem;
  flex: 1;
  flex-wrap: wrap;
}

.enlace {
  padding: 0.35rem 0.7rem;
  border-radius: 5px;
  color: #3e4c59;
  text-decoration: none;
  font-size: 0.9rem;
}

.enlace:hover {
  background: #eef2f4;
}

.enlace.activo {
  background: #2f6f4f;
  color: #fff;
}

.sesion {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.nombre {
  font-size: 0.88rem;
  color: #3e4c59;
}

.salir {
  border: 1px solid #cbd2d9;
  background: #fff;
  border-radius: 5px;
  padding: 0.3rem 0.7rem;
  cursor: pointer;
  font-size: 0.85rem;
}

.contenido {
  flex: 1;
  padding: 1.5rem;
  max-width: 72rem;
  width: 100%;
  margin: 0 auto;
}

.pie {
  padding: 0.8rem 1.5rem;
  border-top: 1px solid #dfe4e8;
  background: #fff;
}

.pie p {
  margin: 0;
  font-size: 0.78rem;
  color: #7b8794;
}
</style>