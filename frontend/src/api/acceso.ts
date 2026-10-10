import { cliente } from './cliente'
import type { Paginado, Permiso, Rol, Usuario } from '@/tipos/api'

export interface DatosUsuario {
  nombre: string
  correo: string
  contrasena?: string | null
  roles: string[]
}

export interface DatosRol {
  nombre: string
  permisos: string[]
}

export const apiAcceso = {
  usuarios: (parametros: { buscar?: string; page?: number; por_pagina?: number } = {}) =>
    cliente.get<Paginado<Usuario>>('/usuarios', { params: parametros }).then((r) => r.data),
  crearUsuario: (datos: DatosUsuario) =>
    cliente.post<{ data: Usuario }>('/usuarios', datos).then((r) => r.data.data),
  editarUsuario: (id: number, datos: Partial<DatosUsuario>) =>
    cliente.put<{ data: Usuario }>(`/usuarios/${id}`, datos).then((r) => r.data.data),
  desactivarUsuario: (id: number) =>
    cliente.patch<{ data: Usuario }>(`/usuarios/${id}/desactivar`).then((r) => r.data.data),
  activarUsuario: (id: number) =>
    cliente.patch<{ data: Usuario }>(`/usuarios/${id}/activar`).then((r) => r.data.data),

  roles: () => cliente.get<{ data: Rol[] }>('/roles').then((r) => r.data.data),
  crearRol: (datos: DatosRol) =>
    cliente.post<{ data: Rol }>('/roles', datos).then((r) => r.data.data),
  editarRol: (id: number, datos: DatosRol) =>
    cliente.put<{ data: Rol }>(`/roles/${id}`, datos).then((r) => r.data.data),
  eliminarRol: (id: number) => cliente.delete(`/roles/${id}`),
  permisos: () => cliente.get<{ data: Permiso[] }>('/permisos').then((r) => r.data.data),
}
