import { cliente } from './cliente'
import type { Equipo, Jugador, Liga, MetaFuente, Paginado } from '@/tipos/api'

export interface FiltrosLigas {
  buscar?: string
  area?: string
  importadas?: boolean
  disponibles?: boolean
}

export interface FiltrosEquipos {
  buscar?: string
  liga_id?: number
  tipo?: 'club' | 'seleccion'
  page?: number
  por_pagina?: number
}

export interface FiltrosJugadores {
  buscar?: string
  equipo_id?: number
  posicion?: string
  orden?: string
  direccion?: 'asc' | 'desc'
  page?: number
  por_pagina?: number
}

/** Quita los valores vacíos para que no viajen como "?buscar=". */
function limpiar<T extends object>(parametros: T): Partial<T> {
  return Object.fromEntries(
    Object.entries(parametros).filter(
      ([, valor]) => valor !== undefined && valor !== null && valor !== '',
    ),
  ) as Partial<T>
}

export const apiFutbol = {
  ligas: (filtros: FiltrosLigas = {}) =>
    cliente
      .get<{ data: Liga[]; meta: MetaFuente }>('/competitions', { params: limpiar(filtros) })
      .then((r) => r.data),

  liga: (id: number | string) =>
    cliente.get<{ data: Liga; meta: MetaFuente }>(`/competitions/${id}`).then((r) => r.data),

  encolarImportacion: (id: number | string) =>
    cliente.post<{ mensaje: string }>(`/competitions/${id}/importar`).then((r) => r.data),

  equipos: (filtros: FiltrosEquipos = {}) =>
    cliente.get<Paginado<Equipo>>('/team', { params: limpiar(filtros) }).then((r) => r.data),

  equipo: (id: number | string) =>
    cliente.get<{ data: Equipo }>(`/team/${id}`).then((r) => r.data.data),

  jugadores: (filtros: FiltrosJugadores = {}) =>
    cliente
      .get<Paginado<Jugador> & { filtros: { posiciones: string[] } }>('/players', {
        params: limpiar(filtros),
      })
      .then((r) => r.data),
}
