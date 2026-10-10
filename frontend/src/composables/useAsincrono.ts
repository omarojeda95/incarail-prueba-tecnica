import { ref, shallowRef, type Ref } from 'vue'
import { ErrorApi, aErrorApi } from '@/api/errores'

/**
 * Estado de carga / error / datos de una llamada asíncrona. Las respuestas que
 * llegan fuera de orden se ignoran: escribir rápido en un buscador nunca
 * muestra resultados viejos.
 */
export function useAsincrono<T, A extends unknown[] = []>(funcion: (...args: A) => Promise<T>) {
  const datos = shallowRef<T | null>(null) as Ref<T | null>
  const cargando = ref(false)
  const error = shallowRef<ErrorApi | null>(null)
  let ultimaLlamada = 0

  async function ejecutar(...args: A): Promise<T | null> {
    const llamada = ++ultimaLlamada
    cargando.value = true
    error.value = null
    try {
      const resultado = await funcion(...args)
      if (llamada === ultimaLlamada) datos.value = resultado
      return resultado
    } catch (e) {
      if (llamada === ultimaLlamada) error.value = aErrorApi(e)
      return null
    } finally {
      if (llamada === ultimaLlamada) cargando.value = false
    }
  }

  return { datos, cargando, error, ejecutar }
}
