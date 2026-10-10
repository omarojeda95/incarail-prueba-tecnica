import { describe, expect, it } from 'vitest'
import { useAsincrono } from '@/composables/useAsincrono'
import { ErrorApi } from '@/api/errores'

const diferida = <T>() => {
  let resolver!: (valor: T) => void
  const promesa = new Promise<T>((r) => (resolver = r))
  return { promesa, resolver }
}

describe('useAsincrono', () => {
  it('solo conserva la respuesta de la última llamada', async () => {
    const lenta = diferida<string>()
    const rapida = diferida<string>()
    const llamadas = [lenta, rapida]
    const { datos, cargando, ejecutar } = useAsincrono(() => llamadas.shift()!.promesa)

    const primera = ejecutar()
    const segunda = ejecutar()
    rapida.resolver('rápida')
    await segunda
    lenta.resolver('lenta')
    await primera

    expect(datos.value).toBe('rápida')
    expect(cargando.value).toBe(false)
  })

  it('expone las fallas como ErrorApi', async () => {
    const { error, ejecutar } = useAsincrono(() =>
      Promise.reject(new ErrorApi(404, 'no_encontrado', 'No existe')),
    )

    await ejecutar()

    expect(error.value?.codigo).toBe('no_encontrado')
  })
})
