import { describe, expect, it } from 'vitest'
import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'
import { ErrorApi, aErrorApi } from '@/api/errores'

function errorAxios(
  estado: number,
  cuerpo: unknown,
  encabezados: Record<string, string> = {},
): AxiosError {
  const respuesta = {
    status: estado,
    data: cuerpo,
    headers: encabezados,
    config: { headers: new AxiosHeaders() },
  } as AxiosResponse
  return new AxiosError('Request failed', 'ERR_BAD_RESPONSE', undefined, undefined, respuesta)
}

describe('aErrorApi', () => {
  it('lee el formato de error de la API', () => {
    const error = aErrorApi(
      errorAxios(422, {
        error: {
          estado: 422,
          codigo: 'validacion_fallida',
          mensaje: 'Los datos enviados no son válidos.',
          detalles: { campos: { correo: ['El correo ya está en uso.'] } },
        },
      }),
    )

    expect(error).toBeInstanceOf(ErrorApi)
    expect(error.codigo).toBe('validacion_fallida')
    expect(error.campo('correo')).toBe('El correo ya está en uso.')
    expect(error.campo('nombre')).toBeUndefined()
  })

  it('entiende el límite de peticiones de football-data', () => {
    const error = aErrorApi(
      errorAxios(
        429,
        {
          error: {
            estado: 429,
            codigo: 'football_data_limite_alcanzado',
            mensaje: 'Límite',
            detalles: { reintentar_en: 42, en_cola: true },
          },
        },
        { 'retry-after': '42' },
      ),
    )

    expect(error.esLimiteAlcanzado).toBe(true)
    expect(error.esProveedorCaido).toBe(false)
    expect(error.reintentarEn).toBe(42)
    expect(error.enCola).toBe(true)
  })

  it('usa la cabecera Retry-After si no viene en el cuerpo', () => {
    const error = aErrorApi(
      errorAxios(
        429,
        { error: { estado: 429, codigo: 'demasiadas_solicitudes', mensaje: 'Demasiadas' } },
        { 'retry-after': '7' },
      ),
    )

    expect(error.reintentarEn).toBe(7)
  })

  it('reconoce las caídas de football-data', () => {
    const error = aErrorApi(
      errorAxios(503, {
        error: { estado: 503, codigo: 'football_data_no_disponible', mensaje: 'Caído' },
      }),
    )

    expect(error.esProveedorCaido).toBe(true)
  })

  it('convierte la falta de respuesta en un error de conexión', () => {
    const error = aErrorApi(new AxiosError('Network Error', 'ERR_NETWORK'))

    expect(error.codigo).toBe('sin_conexion')
    expect(error.estado).toBe(0)
  })
})
