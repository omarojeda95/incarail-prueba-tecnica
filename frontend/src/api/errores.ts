import { isAxiosError } from 'axios'
import type { CuerpoError } from '@/tipos/api'

/** Toda falla que la SPA puede mostrar, normalizada desde el formato de error de la API. */
export class ErrorApi extends Error {
  constructor(
    public readonly estado: number,
    public readonly codigo: string,
    mensaje: string,
    public readonly campos: Record<string, string[]> = {},
    public readonly reintentarEn: number | null = null,
    public readonly enCola = false,
  ) {
    super(mensaje)
    this.name = 'ErrorApi'
  }

  get esLimiteAlcanzado(): boolean {
    return this.estado === 429
  }

  get esProveedorCaido(): boolean {
    return this.codigo.startsWith('football_data_') && !this.esLimiteAlcanzado
  }

  /** Primer mensaje de validación de un campo, para los formularios. */
  campo(nombre: string): string | undefined {
    return this.campos[nombre]?.[0]
  }
}

export function aErrorApi(error: unknown): ErrorApi {
  if (error instanceof ErrorApi) return error

  if (isAxiosError<CuerpoError>(error)) {
    const cuerpo = error.response?.data?.error
    if (cuerpo) {
      const reintentarEn =
        cuerpo.detalles?.reintentar_en ?? (Number(error.response?.headers['retry-after']) || null)
      return new ErrorApi(
        cuerpo.estado,
        cuerpo.codigo,
        cuerpo.mensaje,
        cuerpo.detalles?.campos ?? {},
        reintentarEn,
        cuerpo.detalles?.en_cola ?? false,
      )
    }
    if (!error.response) {
      return new ErrorApi(
        0,
        'sin_conexion',
        'No se pudo conectar con la API. Revisa tu conexión o que el servidor esté levantado.',
      )
    }
    return new ErrorApi(
      error.response.status,
      'error_http',
      `La API respondió con un error (${error.response.status}).`,
    )
  }

  return new ErrorApi(0, 'error_desconocido', 'Ocurrió un error inesperado.')
}
