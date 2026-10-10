import { http } from './http'
import type { Paginated, Permission, Role, User } from '@/types/api'

export interface UserPayload {
  name: string
  email: string
  password?: string | null
  roles: string[]
}

export interface RolePayload {
  name: string
  permissions: string[]
}

export const accessApi = {
  users: (params: { search?: string; page?: number; per_page?: number } = {}) =>
    http.get<Paginated<User>>('/users', { params }).then((r) => r.data),
  createUser: (payload: UserPayload) =>
    http.post<{ data: User }>('/users', payload).then((r) => r.data.data),
  updateUser: (id: number, payload: Partial<UserPayload>) =>
    http.put<{ data: User }>(`/users/${id}`, payload).then((r) => r.data.data),
  deactivateUser: (id: number) =>
    http.patch<{ data: User }>(`/users/${id}/deactivate`).then((r) => r.data.data),
  activateUser: (id: number) =>
    http.patch<{ data: User }>(`/users/${id}/activate`).then((r) => r.data.data),

  roles: () => http.get<{ data: Role[] }>('/roles').then((r) => r.data.data),
  createRole: (payload: RolePayload) =>
    http.post<{ data: Role }>('/roles', payload).then((r) => r.data.data),
  updateRole: (id: number, payload: RolePayload) =>
    http.put<{ data: Role }>(`/roles/${id}`, payload).then((r) => r.data.data),
  deleteRole: (id: number) => http.delete(`/roles/${id}`),
  permissions: () => http.get<{ data: Permission[] }>('/permissions').then((r) => r.data.data),
}
