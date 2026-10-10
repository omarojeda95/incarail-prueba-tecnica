import { describe, expect, it } from 'vitest'
import { positionLabel, positionOrder } from '@/utils/format'

describe('positions', () => {
  it('translates and orders football-data positions', () => {
    expect(positionLabel('Goalkeeper')).toBe('Arquero')
    expect(positionLabel('Centre-Forward')).toBe('Centrodelantero')
    expect(positionLabel('Something New')).toBe('Something New')
    expect(positionLabel(null)).toBe('Sin posición')
    expect(positionOrder('Goalkeeper')).toBeLessThan(positionOrder('Offence'))
    expect(positionOrder(null)).toBe(9)
  })
})
