// Configuración de Nuxt (capa web) del Colegio.
//
// ARQUITECTURA BFF
// ----------------
// Esta aplicación es un "backend for frontend": la API de Laravel es la
// autoridad de la sesión y el navegador nunca habla directo con ella.
//
// El motivo es concreto: la sesión vive en una cookie HttpOnly + Secure +
// SameSite=Lax. Si el navegador llamara a otra origen, la cookie no viajaría
// y habría que resolver el problema con CORS y credenciales, que duplica el
// riesgo de CSRF. Al pasar todo por el mismo origen (este servidor reenvía a
// la API), la cookie se mantiene en una sola origen y el token CSRF se
// reenvía como cabecera `X-XSRF-TOKEN`.
//
// Ver docs/01, sección "Arquitectura BFF".

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  ssr: true,

  devtools: { enabled: false },

  runtimeConfig: {
    // URL de la API de Laravel. Es config PRIVADA: solo la usa el servidor.
    // Nunca debe exponerse al navegador.
    apiBase: process.env.NUXT_API_BASE || 'http://localhost:8000',

    public: {
      // Origen de entrada del portal, por ejemplo
      // https://colegio.hefestostudio.cl
      basePublico: process.env.NUXT_PUBLIC_BASE || 'http://localhost:3000',
    },
  },

  app: {
    head: {
      htmlAttrs: { lang: 'es-CL' },
      title: 'Gestión de Colegio',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content: 'Gestión de matrículas, comunicación con familias y seguimiento de alumnos.',
        },
        // Sin referrer completo: una URL de ficha de alumno identifica a un
        // menor, y no necesita viajar a terceros.
        { name: 'referrer', content: 'strict-origin-when-cross-origin' },
      ],
    },
  },

  // Las solicitudes de datos pasan por el BFF de `server/api`, no por el
  // navegador. El cliente solo llama a rutas del mismo origen.
  nitro: {
    routeRules: {
      '/api/**': { cors: false },
    },
  },
});