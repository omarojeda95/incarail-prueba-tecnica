import { http } from './http'
import type { Competition, Paginated, Player, SourceMeta, Team } from '@/types/api'

export interface CompetitionFilters {
  search?: string
  area?: string
  imported?: boolean
  available?: boolean
}

export interface TeamFilters {
  search?: string
  competition_id?: number
  page?: number
  per_page?: number
}

export interface PlayerFilters {
  search?: string
  team_id?: number
  position?: string
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

/** Drops empty values so they do not travel as "?search=". */
function clean<T extends object>(params: T): Partial<T> {
  return Object.fromEntries(
    Object.entries(params).filter(
      ([, value]) => value !== undefined && value !== null && value !== '',
    ),
  ) as Partial<T>
}

export const footballApi = {
  competitions: (filters: CompetitionFilters = {}) =>
    http
      .get<{ data: Competition[]; meta: SourceMeta }>('/competitions', { params: clean(filters) })
      .then((r) => r.data),

  competition: (id: number | string) =>
    http.get<{ data: Competition; meta: SourceMeta }>(`/competitions/${id}`).then((r) => r.data),

  queueImport: (id: number | string) =>
    http.post<{ message: string }>(`/competitions/${id}/import`).then((r) => r.data),

  teams: (filters: TeamFilters = {}) =>
    http.get<Paginated<Team>>('/team', { params: clean(filters) }).then((r) => r.data),

  team: (id: number | string) => http.get<{ data: Team }>(`/team/${id}`).then((r) => r.data.data),

  players: (filters: PlayerFilters = {}) =>
    http
      .get<Paginated<Player> & { filters: { positions: string[] } }>('/players', {
        params: clean(filters),
      })
      .then((r) => r.data),
}
