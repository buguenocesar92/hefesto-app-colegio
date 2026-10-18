<script setup lang="ts">
/**
 * Inicio: el tablero, que no es igual para todos.
 *
 * Cada rol ve lo que le corresponde y nada más. La razón es práctica: un
 * tablero que le muestra al maestro de comunicaciones los incidentes de
 * conducta lo obliga a él a preguntarse si puede mirar, y el objetivo es
 * justamente que no tenga que decidir eso.
 */
const { usuario, capacidades, esApoderado, nombreColegio } = useSesion();

const secciones = computed(() => {
  const items: Array<{ titulo: string; texto: string; to: string }> = [];

  if (capacidades.value?.envia_comunicaciones) {
    items.push({
      titulo: 'Comunicaciones',
      texto: 'Registre lo que avisa a las familias y lleva el acuse de recibo.',
      to: '/comunicaciones',
    });
  }

  if (capacidades.value?.registra_asistencia) {
    items.push({
      titulo: 'Asistencia',
      texto: 'Registre la presencia del día en los cursos que tiene a cargo.',
      to: '/asistencia',
    });
  }

  if (capacidades.value?.ve_conducta) {
    items.push({
      titulo: 'Conducta',
      texto: 'Incidentes registrados. Es información sensible y no se muestra a todos los roles.',
      to: '/conducta',
    });
  }

  if (capacidades.value?.gestiona_agenda) {
    items.push({
      titulo: 'Agenda',
      texto: 'Entrevistas y reuniones con las familias, y sus actas.',
      to: '/agenda',
    });
  }

  if (esApoderado.value) {
    items.push({
      titulo: 'Mis hijos',
      texto: 'Datos y matrícula de los alumnos asociados a su cuenta.',
      to: '/alumnos',
    });

    items.push({
      titulo: 'Avisos',
      texto: 'Los avisos que el colegio les envió, y cuáles ya confirmaron recepción.',
      to: '/comunicaciones',
    });
  }

  return items;
});
</script>

<template>
  <div>
    <h1>{{ nombreColegio || 'Colegio' }}</h1>

    <p class="saludo">
      Hola, {{ usuario?.nombre_completo }}. Su rol es {{ usuario?.rol_etiqueta }}.
    </p>

    <div v-if="secciones.length" class="rejilla">
      <NuxtLink v-for="seccion in secciones" :key="seccion.to" :to="seccion.to" class="tarjeta">
        <h2>{{ seccion.titulo }}</h2>
        <p>{{ seccion.texto }}</p>
      </NuxtLink>
    </div>

    <p v-else class="vacio">
      Su rol no tiene secciones disponibles por ahora.
    </p>
  </div>
</template>

<style scoped>
h1 {
  margin: 0 0 0.3rem;
  font-size: 1.35rem;
  color: #1f2933;
}

.saludo {
  margin: 0 0 1.5rem;
  color: #52606d;
  font-size: 0.92rem;
}

.rejilla {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
  gap: 0.9rem;
}

.tarjeta {
  background: #fff;
  border: 1px solid #dfe4e8;
  border-radius: 8px;
  padding: 1.1rem;
  text-decoration: none;
  color: inherit;
  display: block;
}

.tarjeta:hover {
  border-color: #2f6f4f;
}

h2 {
  margin: 0 0 0.4rem;
  font-size: 1.02rem;
  color: #2f6f4f;
}

.tarjeta p {
  margin: 0;
  font-size: 0.88rem;
  color: #52606d;
  line-height: 1.45;
}

.vacio {
  color: #7b8794;
  font-size: 0.92rem;
}
</style>