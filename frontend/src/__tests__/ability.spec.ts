import { describe, expect, it } from 'vitest'
import { ability, applyRules } from '@/ability'

describe('ability', () => {
  it('applies the CASL rules sent by the API', () => {
    applyRules([
      { action: 'read', subject: 'Competition' },
      { action: 'import', subject: 'Competition' },
      { action: 'read', subject: 'Team' },
    ])

    expect(ability.can('read', 'Competition')).toBe(true)
    expect(ability.can('import', 'Competition')).toBe(true)
    expect(ability.can('read', 'Team')).toBe(true)
    expect(ability.can('read', 'User')).toBe(false)
    expect(ability.can('manage', 'Role')).toBe(false)
  })

  it('forgets everything on logout', () => {
    applyRules([{ action: 'read', subject: 'Player' }])
    applyRules([])

    expect(ability.can('read', 'Player')).toBe(false)
  })
})
