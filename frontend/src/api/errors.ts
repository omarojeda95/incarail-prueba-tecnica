import { isAxiosError } from 'axios'
import type { ApiErrorBody } from '@/types/api'

/** Every failure the SPA can show, normalized from the API error envelope. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly fields: Record<string, string[]> = {},
    public readonly retryAfter: number | null = null,
    public readonly queued = false,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  get isRateLimited(): boolean {
    return this.status === 429
  }

  get isUpstreamDown(): boolean {
    return this.code.startsWith('football_data_') && !this.isRateLimited
  }

  /** First validation message of a field, for form inputs. */
  field(name: string): string | undefined {
    return this.fields[name]?.[0]
  }
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error

  if (isAxiosError<ApiErrorBody>(error)) {
    const body = error.response?.data?.error
    if (body) {
      const retryAfter =
        body.details?.retry_after ?? (Number(error.response?.headers['retry-after']) || null)
      return new ApiError(
        body.status,
        body.code,
        body.message,
        body.details?.fields ?? {},
        retryAfter,
        body.details?.queued ?? false,
      )
    }
    if (!error.response) {
      return new ApiError(
        0,
        'network_error',
        'No se pudo conectar con la API. Revisa tu conexión o que el servidor esté levantado.',
      )
    }
    return new ApiError(
      error.response.status,
      'http_error',
      `La API respondió con un error (${error.response.status}).`,
    )
  }

  return new ApiError(0, 'unknown_error', 'Ocurrió un error inesperado.')
}
