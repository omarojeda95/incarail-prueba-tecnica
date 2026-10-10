import axios from 'axios'
import { aErrorApi } from './errores'

export const CLAVE_TOKEN = 'incarail.token'

/** Ganchos que registra la sesión, para que este módulo no dependa de Pinia. */
export const ganchosSesion = {
  alPerderSesion: (_mensaje: string) => {},
  alDesactivarCuenta: (_mensaje: string) => {},
}

export const cliente = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8001',
  headers: { Accept: 'application/json' },
  timeout: 30_000,
})

cliente.interceptors.request.use((config) => {
  const token = localStorage.getItem(CLAVE_TOKEN)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

cliente.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    const errorApi = aErrorApi(error)
    const esInicioSesion = error.config?.url?.includes('auth/iniciar-sesion')

    if (errorApi.estado === 401 && !esInicioSesion) ganchosSesion.alPerderSesion(errorApi.message)
    if (errorApi.codigo === 'cuenta_desactivada' && !esInicioSesion) {
      ganchosSesion.alDesactivarCuenta(errorApi.message)
    }

    return Promise.reject(errorApi)
  },
)
