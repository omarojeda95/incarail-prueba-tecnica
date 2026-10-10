import { describe, expect, it } from 'vitest'
import { aplicarReglas, habilidades } from '@/habilidades'

describe('habilidades', () => {
  it('aplica las reglas CASL que envía la API', () => {
    aplicarReglas([
      { action: 'ver', subject: 'Liga' },
      { action: 'importar', subject: 'Liga' },
      { action: 'ver', subject: 'Equipo' },
    ])

    expect(habilidades.can('ver', 'Liga')).toBe(true)
    expect(habilidades.can('importar', 'Liga')).toBe(true)
    expect(habilidades.can('ver', 'Equipo')).toBe(true)
    expect(habilidades.can('ver', 'Usuario')).toBe(false)
    expect(habilidades.can('gestionar', 'Rol')).toBe(false)
  })

  it('olvida todo al cerrar sesión', () => {
    aplicarReglas([{ action: 'ver', subject: 'Jugador' }])
    aplicarReglas([])

    expect(habilidades.can('ver', 'Jugador')).toBe(false)
  })
})
