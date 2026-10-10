import { createMongoAbility, type MongoAbility } from '@casl/ability'
import type { AbilityRule } from '@/types/api'

export type Action = 'read' | 'import' | 'create' | 'update' | 'deactivate' | 'manage'
export type Subject = 'Competition' | 'Team' | 'Player' | 'User' | 'Role' | 'all'
export type AppAbility = MongoAbility<[Action, Subject]>

/**
 * One ability instance for the whole SPA. Its rules come from the API on login
 * (and on reload via /auth/me); the server still enforces every permission.
 */
export const ability: AppAbility = createMongoAbility<[Action, Subject]>([])

export function applyRules(rules: AbilityRule[]): void {
  ability.update(rules as { action: Action; subject: Subject }[])
}
