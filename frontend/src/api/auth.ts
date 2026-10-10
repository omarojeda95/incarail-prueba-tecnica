import { http } from './http'
import type { LoginResponse, Session } from '@/types/api'

export const authApi = {
  login: (email: string, password: string) =>
    http.post<LoginResponse>('/auth/login', { email, password }).then((r) => r.data),
  me: () => http.get<Session>('/auth/me').then((r) => r.data),
  logout: () => http.post('/auth/logout'),
}
