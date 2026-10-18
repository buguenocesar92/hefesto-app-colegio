/**
 * Estado de la sesión del colegio.
 *
 * LA API ES LA ÚNICA AUTORIDAD
 * ----------------------------
 * Esta composable no guarda ningún dato de autorización propio: solo refleja
 * lo que la API responde. Si alguien manipulara este estado en el navegador,
 * no ganaría nada, porque cada petición vuelve a preguntar a la API.
 *
 * `capacidades` existe para decidir qué menús mostrar, NO para proteger nada.
 * Que un botón no se vea no es una medida de seguridad: el endpoint exige el
 * permiso igual, y si falta, responde 404.
 */
export type RolUsuario = 'direccion' | 'administracion' | 'docente' | 'comunicaciones' | 'orientacion' | 'apoderado';

export interface UsuarioSesion {
  id: string;
  correo: string;
  nombre_completo: string;
  rol: RolUsuario;
  rol_etiqueta: string;
  es_del_colegio: boolean;
  ve_conducta: boolean;
  envia_comunicaciones: boolean;
  activo: boolean;
}

export interface Capacidades {
  administra: boolean;
  ve_todos_los_alumnos: boolean;
  registra_asistencia: boolean;
  registra_conducta: boolean;
  ve_conducta: boolean;
  envia_comunicaciones: boolean;
  gestiona_agenda: boolean;
}

export interface DatosSesion {
  usuario: UsuarioSesion;
  colegio: { nombre: string | null; slug: string | null };
  capacidades: Capacidades;
}

export function useSesion() {
  const sesion = useState<DatosSesion | null>('sesion', () => null);
  const cargando = useState<boolean>('sesion-cargando', () => false);

  /**
   * Pregunta a la API quién está conectado.
   *
   * En el servidor usa `useRequestFetch()`, que reenvía las cookies del
   * navegador al BFF. Sin eso, la primera carga en modo SSR siempre
   * respondería "no conectado" y la pantalla de entrada aparecería siempre.
   */
  async function cargar(): Promise<DatosSesion | null> {
    cargando.value = true;

    try {
      const respuesta = await useRequestFetch()<DatosSesion>('/api/v1/auth/usuario');
      sesion.value = respuesta;
    } catch {
      sesion.value = null;
    } finally {
      cargando.value = false;
    }

    return sesion.value;
  }

  async function salir(): Promise<void> {
    await $fetch('/api/v1/auth/logout', { method: 'POST' });
    sesion.value = null;

    await navigateTo('/entrar');
  }

  const usuario = computed<UsuarioSesion | null>(() => sesion.value?.usuario ?? null);
  const capacidades = computed<Capacidades | null>(() => sesion.value?.capacidades ?? null);
  const nombreColegio = computed<string>(() => sesion.value?.colegio.nombre ?? '');
  const esDelColegio = computed<boolean>(() => usuario.value?.es_del_colegio === true);
  const esApoderado = computed<boolean>(() => usuario.value?.rol === 'apoderado');

  return {
    sesion,
    usuario,
    capacidades,
    nombreColegio,
    esDelColegio,
    esApoderado,
    cargando,
    cargar,
    salir,
  };
}