import { describe, expect, it } from 'vitest'
import { useAsync } from '@/composables/useAsync'
import { ApiError } from '@/api/errors'

const deferred = <T>() => {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((r) => (resolve = r))
  return { promise, resolve }
}

describe('useAsync', () => {
  it('keeps only the answer of the latest call', async () => {
    const slow = deferred<string>()
    const fast = deferred<string>()
    const calls = [slow, fast]
    const { data, loading, run } = useAsync(() => calls.shift()!.promise)

    const first = run()
    const second = run()
    fast.resolve('fast')
    await second
    slow.resolve('slow')
    await first

    expect(data.value).toBe('fast')
    expect(loading.value).toBe(false)
  })

  it('exposes failures as ApiError', async () => {
    const { error, run } = useAsync(() =>
      Promise.reject(new ApiError(404, 'not_found', 'No existe')),
    )

    await run()

    expect(error.value?.code).toBe('not_found')
  })
})
