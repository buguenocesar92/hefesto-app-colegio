<script setup lang="ts">
/**
 * Pantalla de entrada.
 *
 * El ingreso completo ocurre en Authentik. Esta pantalla solo manda a allá y
 * muestra el error si algo falló. No hay formulario de usuario y contraseña
 * en esta aplicación, y no lo va a haber: las contraseñas de familias y de
 * empleados no viven acá.
 */
const ruta = useRoute();

const cargando = ref(false);

const error = computed<string | null>(() => {
  const valor = ruta.query.error;

  return typeof valor === 'string' ? valor : null;
});

async function entrar(): Promise<void> {
  cargando.value = true;

  try {
    const destino = typeof ruta.query.redirigir_a === 'string' ? ruta.query.redirigir_a : '';

    await navigateTo(destino ? `/api/v1/auth/login?redirigir_a=${encodeURIComponent(destino)}` : '/api/v1/auth/login', {
      external: true,
    });
  } finally {
    cargando.value = false;
  }
}
</script>

<template>
  <main class="entrar">
    <section class="tarjeta">
      <h1>Gestión de Colegio</h1>

      <p class="lema">
        Matrículas, comunicación con las familias y seguimiento de alumnos.
      </p>

      <p v-if="error" class="error" role="alert">
        {{ error }}
      </p>

      <button type="button" :disabled="cargando" @click="entrar">
        {{ cargando ? 'Redirigiendo...' : 'Ingresar con la cuenta del colegio' }}
      </button>

      <p class="nota">
        El acceso lo administra el colegio. Si no puede ingresar, hable con la
        secretaría.
      </p>
    </section>
  </main>
</template>

<style scoped>
.entrar {
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: #f4f6f8;
  padding: 2rem;
}

.tarjeta {
  background: #fff;
  border: 1px solid #dfe4e8;
  border-radius: 10px;
  padding: 2.5rem;
  max-width: 26rem;
  width: 100%;
  text-align: center;
}

h1 {
  margin: 0 0 0.5rem;
  font-size: 1.4rem;
  color: #1f2933;
}

.lema {
  color: #52606d;
  font-size: 0.95rem;
  margin: 0 0 1.5rem;
}

button {
  width: 100%;
  padding: 0.7rem 1rem;
  border: 0;
  border-radius: 6px;
  background: #2f6f4f;
  color: #fff;
  font-size: 1rem;
  cursor: pointer;
}

button:disabled {
  background: #93b0a0;
  cursor: progress;
}

.error {
  background: #fdeaea;
  border: 1px solid #f0b4b4;
  color: #8a2020;
  border-radius: 6px;
  padding: 0.6rem 0.8rem;
  font-size: 0.9rem;
}

.nota {
  color: #7b8794;
  font-size: 0.85rem;
  margin: 1.2rem 0 0;
}
</style>