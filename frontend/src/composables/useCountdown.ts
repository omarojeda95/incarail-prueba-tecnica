import { computed, onBeforeUnmount, ref } from 'vue'

/** Seconds left until something can be retried (e.g. the football-data rate limit). */
export function useCountdown() {
  const remaining = ref(0)
  let timer: ReturnType<typeof setInterval> | undefined

  function start(seconds: number, onDone?: () => void): void {
    stop()
    remaining.value = Math.max(0, Math.ceil(seconds))
    timer = setInterval(() => {
      remaining.value = Math.max(0, remaining.value - 1)
      if (remaining.value === 0) {
        stop()
        onDone?.()
      }
    }, 1000)
  }

  function stop(): void {
    clearInterval(timer)
    timer = undefined
  }

  onBeforeUnmount(stop)

  return { remaining, running: computed(() => remaining.value > 0), start, stop }
}
