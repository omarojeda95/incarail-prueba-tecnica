import { customRef, type Ref } from 'vue'

/** A ref whose updates are delayed: ideal for search inputs that hit the API. */
export function useDebouncedRef<T>(initial: T, delay = 350): Ref<T> {
  let timeout: ReturnType<typeof setTimeout> | undefined
  let value = initial

  return customRef((track, trigger) => ({
    get() {
      track()
      return value
    },
    set(next: T) {
      clearTimeout(timeout)
      timeout = setTimeout(() => {
        value = next
        trigger()
      }, delay)
    },
  }))
}
