/**
 * Tipos de la API.
 *
 * COPIA MANUAL, Y ESO ES UN RIESGUE CONOCIDO
 * -------------------------------------------
 * Estos tipos reflejan lo que devuelven los Resources de Laravel, pero
 * están escritos a mano: si un Resource cambia, este archivo deja de
 * coincidir y TypeScript no se queja, porque son dos cosas distintas.
 *
 * `npm run typecheck` NO detecta esa diferencia. Solo lo detecta que la
 * pantalla se vea rara. Está anotado como pendiente en docs/10.
 *
 * La alternativa sería generar los tipos desde la API, que es lo correcto,
 * pero es trabajo que quedó fuera de este corte.
 */

/** Envoltura de lista paginada que devuelve Laravel. */
export interface Paginado<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/** Alumno. La ficha completa incluye datos sensibles: exige permiso aparte. */
export interface Alumno {
  id: string;
  nombre: string;
  apellido: string;
  nombre_completo: string;
  iniciales: string;
  edad: number;
  rut: string | null;
  fecha_nacimiento: string | null;
  sexo: string;
  direccion: string | null;
  telefono: string | null;
  correo: string | null;
  fecha_ingreso: string | null;
  activo: boolean;
  matricula?: {
    id: string;
    numero_matricula: string;
    anio_escolar: number;
    estado: string;
    estado_etiqueta: string;
    curso: { id: string; nombre: string; sigla: string } | null;
  };
  contactos_emergencia?: Array<{
    id: string;
    nombre: string;
    parentesco: string;
    telefono: string;
    prioridad: number;
  }>;
  apoderados?: Array<{
    id: string;
    nombre_completo: string;
    vinculo: string;
    es_responsable_legal: boolean;
  }>;
}

/** Comunicación, con su estado de entrega. */
export interface Comunicacion {
  id: string;
  referencia: string;
  titulo: string;
  cuerpo: string;
  canal: string;
  canal_etiqueta: string;
  tipo_destinatario: string;
  tipo_destinatario_etiqueta: string;
  estado: string;
  estado_etiqueta: string;
  requiere_acuse: boolean;
  enviada_en: string | null;
  curso?: { id: string; nombre: string; sigla: string } | null;
  nivel?: { id: string; nombre: string } | null;
  remitente?: { id: string; nombre_completo: string; rol: string } | null;
  destinatarios?: Array<{ apoderado_id: string; nombre_completo: string | null }>;
  entrega?: {
    total_destinatarios: number;
    total_recibos: number;
    pendientes: number;
    porcentaje_acuse: number;
  };
  recibos?: Array<{
    apoderado_id: string;
    nombre_completo: string | null;
    recibido_en: string | null;
    medio: string;
  }>;
}

/** Registro de asistencia de un alumno en un día. */
export interface Asistencia {
  id: string;
  alumno_id: string;
  curso_id: string;
  fecha: string;
  estado: string;
  estado_etiqueta: string;
  cuenta_como_presente: boolean;
  es_ausencia: boolean;
  justificacion: string | null;
  alumno?: { id: string; nombre_completo: string; iniciales: string } | null;
  registrado_por?: { id: string; nombre_completo: string } | null;
}

/** Incidente de conducta. Solo lo ven los roles con permiso de lectura. */
export interface Incidente {
  id: string;
  alumno_id: string;
  curso_id: string;
  fecha: string;
  categoria: string;
  categoria_etiqueta: string;
  gravedad: string;
  gravedad_etiqueta: string;
  descripcion: string;
  medida: string | null;
  notificado_apoderado: boolean;
  comunicacion_id: string | null;
  exige_comunicacion: boolean;
  alumno?: { id: string; nombre_completo: string; iniciales: string } | null;
  curso?: { id: string; sigla: string; nombre: string } | null;
}

/** Entrevista o reunión con una familia. */
export interface Entrevista {
  id: string;
  tipo: string;
  fecha: string;
  hora: string;
  lugar: string | null;
  estado: string;
  estado_etiqueta: string;
  motivo: string;
  alumno?: { id: string; nombre_completo: string; iniciales: string } | null;
  apoderado?: { id: string; nombre_completo: string } | null;
  profesional?: { id: string; nombre_completo: string; cargo: string } | null;
  tiene_acta: boolean;
}

/** Curso. */
export interface Curso {
  id: string;
  nombre: string;
  sigla: string;
  orden: number;
  activo: boolean;
  nivel?: { id: string; nombre: string } | null;
}

/** Matrícula. */
export interface Matricula {
  id: string;
  numero_matricula: string;
  anio_escolar: number;
  estado: string;
  estado_etiqueta: string;
  fecha_matricula: string | null;
  observaciones: string | null;
  alumno?: { id: string; nombre_completo: string; iniciales: string; rut: string | null } | null;
  curso?: { id: string; nombre: string; sigla: string; nivel: { id: string; nombre: string } | null } | null;
}