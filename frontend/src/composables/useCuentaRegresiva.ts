import { computed, onBeforeUnmount, ref } from 'vue'

export function useCuentaRegresiva() {
  const restantes = ref(0)
  let intervalo: ReturnType<typeof setInterval> | undefined

  function iniciar(segundos: number, alTerminar?: () => void): void {
    detener()
    restantes.value = Math.max(0, Math.ceil(segundos))
    intervalo = setInterval(() => {
      restantes.value = Math.max(0, restantes.value - 1)
      if (restantes.value === 0) {
        detener()
        alTerminar?.()
      }
    }, 1000)
  }

  function detener(): void {
    clearInterval(intervalo)
    intervalo = undefined
  }

  onBeforeUnmount(detener)

  return { restantes, activa: computed(() => restantes.value > 0), iniciar, detener }
}
