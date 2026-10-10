import { describe, expect, it } from 'vitest'
import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'
import { ApiError, toApiError } from '@/api/errors'

function axiosError(
  status: number,
  data: unknown,
  headers: Record<string, string> = {},
): AxiosError {
  const response = {
    status,
    data,
    headers,
    config: { headers: new AxiosHeaders() },
  } as AxiosResponse
  return new AxiosError('Request failed', 'ERR_BAD_RESPONSE', undefined, undefined, response)
}

describe('toApiError', () => {
  it('reads the API error envelope', () => {
    const error = toApiError(
      axiosError(422, {
        error: {
          status: 422,
          code: 'validation_failed',
          message: 'Los datos enviados no son válidos.',
          details: { fields: { email: ['El correo ya está en uso.'] } },
        },
      }),
    )

    expect(error).toBeInstanceOf(ApiError)
    expect(error.code).toBe('validation_failed')
    expect(error.field('email')).toBe('El correo ya está en uso.')
    expect(error.field('name')).toBeUndefined()
  })

  it('understands the football-data rate limit', () => {
    const error = toApiError(
      axiosError(
        429,
        {
          error: {
            status: 429,
            code: 'football_data_rate_limited',
            message: 'Límite',
            details: { retry_after: 42, queued: true },
          },
        },
        { 'retry-after': '42' },
      ),
    )

    expect(error.isRateLimited).toBe(true)
    expect(error.isUpstreamDown).toBe(false)
    expect(error.retryAfter).toBe(42)
    expect(error.queued).toBe(true)
  })

  it('falls back to the Retry-After header', () => {
    const error = toApiError(
      axiosError(
        429,
        { error: { status: 429, code: 'too_many_requests', message: 'Demasiadas' } },
        { 'retry-after': '7' },
      ),
    )

    expect(error.retryAfter).toBe(7)
  })

  it('flags football-data outages', () => {
    const error = toApiError(
      axiosError(503, {
        error: { status: 503, code: 'football_data_unavailable', message: 'Caído' },
      }),
    )

    expect(error.isUpstreamDown).toBe(true)
  })

  it('turns a missing response into a network error', () => {
    const error = toApiError(new AxiosError('Network Error', 'ERR_NETWORK'))

    expect(error.code).toBe('network_error')
    expect(error.status).toBe(0)
  })
})
