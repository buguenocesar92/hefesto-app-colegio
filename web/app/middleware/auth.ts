/**
 * Middleware de rutas que exigen sesión.
 *
 * Se aplica página por página con `definePageMeta({ middleware: 'auth' })`.
 * Si la API responde que no hay sesión, se va a la pantalla de entrada
 * guardando a dónde quería ir la persona, para volver ahí después.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  // La pantalla de entrada no puede exigir lo que está por starters.
  if (to.path === '/entrar') {
    return;
  }

  const { cargar, usuario } = useSesion();

  await cargar();

  if (!usuario.value) {
    return navigateTo({
      path: '/entrar',
      query: { redirigir_a: to.fullPath },
    });
  }
});