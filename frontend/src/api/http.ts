import axios from 'axios'
import { toApiError } from './errors'

export const TOKEN_KEY = 'incarail.token'

/** Hooks the auth store registers, so this module does not depend on Pinia. */
export const sessionHooks = {
  onUnauthenticated: (_message: string) => {},
  onAccountDisabled: (_message: string) => {},
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8001',
  headers: { Accept: 'application/json' },
  timeout: 30_000,
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const apiError = toApiError(error)
    const isLogin = error.config?.url?.includes('auth/login')

    if (apiError.status === 401 && !isLogin) sessionHooks.onUnauthenticated(apiError.message)
    if (apiError.code === 'account_disabled' && !isLogin)
      sessionHooks.onAccountDisabled(apiError.message)

    return Promise.reject(apiError)
  },
)
