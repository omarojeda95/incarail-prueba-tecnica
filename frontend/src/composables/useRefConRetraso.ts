import { customRef, type Ref } from 'vue'

/** Un ref cuyos cambios se aplican con retraso: ideal para buscadores que llaman a la API. */
export function useRefConRetraso<T>(inicial: T, retraso = 350): Ref<T> {
  let temporizador: ReturnType<typeof setTimeout> | undefined
  let valor = inicial

  return customRef((rastrear, disparar) => ({
    get() {
      rastrear()
      return valor
    },
    set(nuevo: T) {
      clearTimeout(temporizador)
      temporizador = setTimeout(() => {
        valor = nuevo
        disparar()
      }, retraso)
    },
  }))
}
