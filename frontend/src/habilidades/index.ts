import { createMongoAbility, type MongoAbility } from '@casl/ability'
import type { ReglaCasl } from '@/tipos/api'

export type Accion = 'ver' | 'importar' | 'crear' | 'editar' | 'desactivar' | 'gestionar'
export type Sujeto = 'Liga' | 'Equipo' | 'Jugador' | 'Usuario' | 'Rol' | 'all'
export type HabilidadesApp = MongoAbility<[Accion, Sujeto]>

/** Las reglas vienen de la API; el servidor igual verifica cada permiso. */
export const habilidades: HabilidadesApp = createMongoAbility<[Accion, Sujeto]>([])

export function aplicarReglas(reglas: ReglaCasl[]): void {
  habilidades.update(reglas as { action: Accion; subject: Sujeto }[])
}
