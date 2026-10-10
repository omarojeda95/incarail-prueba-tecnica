// Shapes returned by the Laravel API (see backend/app/Http/Resources).

export interface PageMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PageMeta
}

export interface SourceMeta {
  source: 'database' | 'football-data'
  stale: boolean
  synced_at?: string
  retry_after?: number
}

export interface Competition {
  id: number
  name: string
  code: string | null
  type: string | null
  emblem: string | null
  plan: string | null
  available: boolean
  area: { name: string | null; code: string | null; flag: string | null }
  season: { start: string | null; end: string | null; current_matchday: number | null }
  imported: boolean
  teams_synced_at: string | null
  teams_count?: number
  teams?: Team[]
  last_import?: {
    teams_count: number
    players_count: number
    trigger: string
    imported_at: string
  } | null
}

export interface TeamCompetition {
  id: number
  name: string
  code: string | null
  emblem: string | null
  season: string | null
}

export interface Team {
  id: number
  name: string
  short_name: string | null
  tla: string | null
  crest: string | null
  address: string | null
  website: string | null
  founded: number | null
  club_colors: string | null
  venue: string | null
  area: string | null
  coach: string | null
  players_count?: number
  competitions?: TeamCompetition[]
  players?: Player[]
}

export interface Player {
  id: number
  name: string
  position: string | null
  shirt_number: number | null
  date_of_birth: string | null
  age: number | null
  nationality: string | null
  team?: Pick<Team, 'id' | 'name' | 'short_name' | 'crest'> | null
}

export interface AbilityRule {
  action: string
  subject: string
}

export interface User {
  id: number
  name: string
  email: string
  is_active: boolean
  deactivated_at: string | null
  roles?: string[]
  created_at: string | null
}

export interface Role {
  id: number
  name: string
  protected: boolean
  permissions: string[]
  users_count?: number
}

export interface Permission {
  name: string
  action: string
  subject: string
  description: string
}

export interface Session {
  user: User
  abilities: AbilityRule[]
}

export interface LoginResponse extends Session {
  token_type: 'Bearer'
  access_token: string
  expires_at: string | null
}

export interface ApiErrorBody {
  error: {
    status: number
    code: string
    message: string
    details?: {
      fields?: Record<string, string[]>
      retry_after?: number
      queued?: boolean
    }
  }
}
