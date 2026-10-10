import { cliente } from './cliente'
import type { RespuestaInicioSesion, Sesion } from '@/tipos/api'

export const apiAutenticacion = {
  iniciarSesion: (correo: string, contrasena: string) =>
    cliente
      .post<RespuestaInicioSesion>('/auth/iniciar-sesion', { correo, contrasena })
      .then((r) => r.data),
  sesion: () => cliente.get<Sesion>('/auth/sesion').then((r) => r.data),
  cerrarSesion: () => cliente.post('/auth/cerrar-sesion'),
}
