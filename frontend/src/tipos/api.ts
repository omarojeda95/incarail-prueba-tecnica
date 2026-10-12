// Formas que devuelve la API de Laravel (ver backend/app/Http/Resources).

/** Paginación de Laravel (las claves son del framework). */
export interface MetaPagina {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginado<T> {
  data: T[]
  meta: MetaPagina
}

export interface MetaFuente {
  fuente: 'base_de_datos' | 'football-data'
  desactualizado: boolean
  sincronizado_en?: string
  reintentar_en?: number
}

export interface Liga {
  id: number
  nombre: string
  codigo: string | null
  tipo: string | null
  emblema: string | null
  plan: string | null
  disponible: boolean
  area: { nombre: string | null; codigo: string | null; bandera: string | null }
  temporada: { inicio: string | null; fin: string | null; jornada_actual: number | null }
  importada: boolean
  equipos_sincronizados_en: string | null
  cantidad_equipos?: number
  equipos?: Equipo[]
  ultima_importacion?: {
    cantidad_equipos: number
    cantidad_jugadores: number
    origen: string
    importado_en: string
  } | null
}

export interface LigaDeEquipo {
  id: number
  nombre: string
  codigo: string | null
  emblema: string | null
  temporada: string | null
}

export interface Equipo {
  id: number
  nombre: string
  tipo: 'club' | 'seleccion'
  nombre_corto: string | null
  sigla: string | null
  escudo: string | null
  direccion: string | null
  sitio_web: string | null
  fundacion: number | null
  colores: string | null
  estadio: string | null
  area: string | null
  entrenador: string | null
  cantidad_jugadores?: number
  ligas?: LigaDeEquipo[]
  jugadores?: Jugador[]
}

export interface Jugador {
  id: number
  nombre: string
  posicion: string | null
  numero_camiseta: number | null
  fecha_nacimiento: string | null
  edad: number | null
  nacionalidad: string | null
  equipo?: Pick<Equipo, 'id' | 'nombre' | 'nombre_corto' | 'escudo'> | null
}

/** Regla CASL: las claves "action" y "subject" las define la librería. */
export interface ReglaCasl {
  action: string
  subject: string
}

export interface Usuario {
  id: number
  nombre: string
  correo: string
  activo: boolean
  desactivado_en: string | null
  roles?: string[]
  creado_en: string | null
}

export interface Rol {
  id: number
  nombre: string
  protegido: boolean
  permisos: string[]
  cantidad_usuarios?: number
}

export interface Permiso {
  nombre: string
  accion: string
  sujeto: string
  descripcion: string
}

export interface Sesion {
  usuario: Usuario
  habilidades: ReglaCasl[]
}

export interface RespuestaInicioSesion extends Sesion {
  tipo_token: 'Bearer'
  token_acceso: string
  expira_en: string | null
}

export interface CuerpoError {
  error: {
    estado: number
    codigo: string
    mensaje: string
    detalles?: {
      campos?: Record<string, string[]>
      reintentar_en?: number
      en_cola?: boolean
    }
  }
}
