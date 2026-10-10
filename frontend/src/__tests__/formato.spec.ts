import { describe, expect, it } from 'vitest'
import { etiquetaPosicion, ordenPosicion } from '@/utilidades/formato'

describe('posiciones', () => {
  it('traduce y ordena las posiciones de football-data', () => {
    expect(etiquetaPosicion('Goalkeeper')).toBe('Arquero')
    expect(etiquetaPosicion('Centre-Forward')).toBe('Centrodelantero')
    expect(etiquetaPosicion('Algo Nuevo')).toBe('Algo Nuevo')
    expect(etiquetaPosicion(null)).toBe('Sin posición')
    expect(ordenPosicion('Goalkeeper')).toBeLessThan(ordenPosicion('Offence'))
    expect(ordenPosicion(null)).toBe(9)
  })
})
