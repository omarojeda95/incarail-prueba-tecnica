import { ref, shallowRef, type Ref } from 'vue'
import { ApiError, toApiError } from '@/api/errors'

/**
 * Loading / error / data state for one async call. Out-of-order responses are
 * ignored, so typing fast in a search box never shows stale results.
 */
export function useAsync<T, A extends unknown[] = []>(fn: (...args: A) => Promise<T>) {
  const data = shallowRef<T | null>(null) as Ref<T | null>
  const loading = ref(false)
  const error = shallowRef<ApiError | null>(null)
  let lastCall = 0

  async function run(...args: A): Promise<T | null> {
    const call = ++lastCall
    loading.value = true
    error.value = null
    try {
      const result = await fn(...args)
      if (call === lastCall) data.value = result
      return result
    } catch (e) {
      if (call === lastCall) error.value = toApiError(e)
      return null
    } finally {
      if (call === lastCall) loading.value = false
    }
  }

  return { data, loading, error, run }
}
