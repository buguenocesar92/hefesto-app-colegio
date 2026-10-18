import {
  appendResponseHeader,
  defineEventHandler,
  getCookie,
  getMethod,
  getRequestHeader,
  getRequestURL,
  sendRedirect,
  setResponseStatus,
} from 'h3';

/**
 * BFF: reenvía `/api/**` a la API de Laravel.
 *
 * QUÉ HACE Y POR QUÉ
 * ------------------
 * 1. Copia las cookies de la petición del navegador hacia la API. La cookie
 *    de sesión es HttpOnly: el navegador no la puede leer, pero sí la puede
 *    reenviar, que es exactamente lo que hace este archivo.
 *
 * 2. Copia la cookie `XSRF-TOKEN` como cabecera `X-XSRF-TOKEN`. Laravel
 *    acepta esa cabecera para validar el token CSRF. Sin esto, toda
 *    escritura desde el navegador sería rechazada.
 *
 * 3. Copia las cookies de la respuesta de vuelta (por ejemplo, cuando la
 *    API renueva la sesión).
 *
 * 4. Redirige al navegador cuando la petición es la respuesta de Authentik:
 *    el `callback` devuelve JSON, pero en el navegador tiene que ser una
 *    navegación, no un JSON.
 *
 * POR QUÉ EXISTE ESTA CAPA Y NO UN LLAMADO DIRECTO A LA API
 * -------------------------------------------------------
 * La sesión vive en una cookie HttpOnly + SameSite=Lax. Si el navegador
 * llamara a otra origen, la cookie no viajaría y habría que resolver el
 * problema con CORS y credenciales, que además duplica el riesgo de CSRF.
 * Al pasar todo por el mismo origen, la cookie se mantiene en una sola
 * origen y el token CSRF viaja como cabecera.
 *
 * El navegador nunca ve la URL interna de la API ni habla directo con ella.
 */
export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event);
  const url = getRequestURL(event);
  const metodo = getMethod(event).toUpperCase();

  // `/api/v1/alumnos` -> `v1/alumnos`
  const ruta = url.pathname.replace(/^\/api\/?/, '');

  const destino = new URL(`${String(config.apiBase).replace(/\/+$/, '')}/${ruta}`);
  destino.search = url.search;

  const cabeceras: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };

  const cookies = getRequestHeader(event, 'cookie');

  if (cookies) {
    cabeceras.cookie = cookies;
  }

  const tokenCsrf = getCookie(event, 'XSRF-TOKEN');

  if (tokenCsrf) {
    // Laravel guarda el valor con la cookie codificada en URL.
    cabeceras['X-XSRF-TOKEN'] = decodeURIComponent(tokenCsrf);
  }

  let respuesta: Response;

  try {
    respuesta = await fetch(destino.toString(), {
      method: metodo,
      headers: cabeceras,
      // El cuerpo solo existe en métodos que lo tienen. Además, reenviarlo
      // tal cual es lo que evita tener que entender el formato acá: la API
      // sigue siendo la que valida.
      body: metodo === 'GET' || metodo === 'HEAD' ? undefined : await cuerpoDe(event),
      redirect: 'manual',
    });
  } catch (error) {
    setResponseStatus(event, 502);

    return {
      mensaje: 'No se pudo contactar a la API del colegio. Intente en unos segundos.',
      detalle: error instanceof Error ? error.message : String(error),
    };
  }

  const esCallbackDeAuthentik = ruta === 'v1/auth/callback' && metodo === 'GET';

  if (esCallbackDeAuthentik) {
    return manejarCallbackDeAuthentik(event, respuesta);
  }

  propagarCookiesDeRespuesta(event, respuesta);

  setResponseStatus(event, respuesta.status);
  appendResponseHeader(
    event,
    'content-type',
    respuesta.headers.get('content-type') ?? 'application/json',
  );

  // La sesión no debe quedar en cachés intermedias: el contenido depende de
  // quién esté conectado, y estos datos son de menores.
  appendResponseHeader(event, 'cache-control', 'private, no-store');

  return await respuesta.text();
});

/**
 * El callback de Authentik se responde con una navegación, no con un JSON.
 */
async function manejarCallbackDeAuthentik(
  event: Parameters<typeof sendRedirect>[0],
  respuesta: Response,
) {
  propagarCookiesDeRespuesta(event, respuesta);

  const cuerpo = await respuesta.text();

  if (!respuesta.ok) {
    let mensaje = 'No se pudo iniciar sesión.';

    try {
      const datos = JSON.parse(cuerpo) as { mensaje?: string };
      mensaje = datos.mensaje ?? mensaje;
    } catch {
      // Se deja el mensaje genérico: la respuesta de la API no es JSON.
    }

    return sendRedirect(event, `/entrar?error=${encodeURIComponent(mensaje)}`, 302);
  }

  let destino = '/';

  try {
    const datos = JSON.parse(cuerpo) as { redirigir_a?: string };
    destino = destinoSeguro(datos.redirigir_a);
  } catch {
    // Cuerpo ilegible: se vuelve al inicio, que ya exige sesión y, si no
    // hay, devuelve a la pantalla de entrada.
  }

  return sendRedirect(event, destino, 302);
}

/**
 * Reenvía las cookies que la API haya puesto o cambiado.
 *
 * Se usa `getSetCookie()` cuando existe, porque varias cookies no se pueden
 * juntar en una sola cabecera `set-cookie`.
 */
function propagarCookiesDeRespuesta(
  event: Parameters<typeof appendResponseHeader>[0],
  respuesta: Response,
) {
  const cabeceras = respuesta.headers as Headers & { getSetCookie?: () => string[] };

  const cookies = typeof cabeceras.getSetCookie === 'function'
    ? cabeceras.getSetCookie()
    : [respuesta.headers.get('set-cookie')].filter((valor): valor is string => Boolean(valor));

  for (const cookie of cookies) {
    appendResponseHeader(event, 'set-cookie', cookie);
  }
}

/**
 * Lee el cuerpo de la petición como texto.
 *
 * Se reenvía el texto tal cual, sin interpretarlo: la API sigue siendo la
 * que decide si los datos son válidos.
 */
async function cuerpoDe(event: Parameters<typeof getMethod>[0]): Promise<string | undefined> {
  const bruto = await event.req.text();

  return bruto.length > 0 ? bruto : undefined;
}

/**
 * Solo se aceptan rutas internas como destino.
 *
 * Una URL completa o un `//sitio-falso.example` convertirían el ingreso en
 * una redirección abierta: el atacante manda a la víctima a un sitio falso
 * justo después de iniciar sesión, que es el peor momento posible.
 *
 * La API también lo valida (ver AuthController::destinoSeguro). Se repite acá
 * porque el BFF es el primero que recibe la respuesta y decide a dónde
 * llevar al navegador; las dos capas no se estorban.
 */
function destinoSeguro(candidato: unknown): string {
  if (typeof candidato !== 'string' || candidato === '') {
    return '/';
  }

  if (! candidato.startsWith('/')) {
    return '/';
  }

  if (candidato.startsWith('//') || candidato.startsWith('/\\')) {
    return '/';
  }

  return candidato;
}