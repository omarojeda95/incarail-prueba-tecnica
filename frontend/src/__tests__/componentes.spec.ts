import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Component } from 'vue'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import { ErrorApi } from '@/api/errores'
import AlertaError from '@/components/AlertaError.vue'
import EstadoAsincrono from '@/components/EstadoAsincrono.vue'

const vuetify = createVuetify()
const montar = (
  componente: Component,
  props: Record<string, unknown>,
  slots: Record<string, string> = {},
) => mount(componente, { props, slots, global: { plugins: [vuetify] } })

describe('AlertaError', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('ante el límite de football-data muestra la cuenta regresiva y reintenta solo al terminar', async () => {
    const error = new ErrorApi(
      429,
      'football_data_limite_alcanzado',
      'Ya se usaron las 10 consultas.',
      {},
      2,
      true,
    )
    const alerta = montar(AlertaError, { error, reintentable: true })

    expect(alerta.text()).toContain('Límite de consultas alcanzado')
    expect(alerta.text()).toContain('Ya se usaron las 10 consultas.')
    expect(alerta.text()).toContain('Reintentaremos automáticamente en 3 s')

    await vi.advanceTimersByTimeAsync(3000)

    expect(alerta.emitted('reintentar')).toHaveLength(1)
  })

  it('sin permiso muestra un mensaje claro y no reintenta', async () => {
    const error = new ErrorApi(403, 'sin_permiso', 'No tienes permiso para realizar esta acción.')
    const alerta = montar(AlertaError, { error, reintentable: true })

    await vi.advanceTimersByTimeAsync(5000)

    expect(alerta.text()).toContain('Sin permiso')
    expect(alerta.text()).not.toContain('Reintentaremos')
    expect(alerta.emitted('reintentar')).toBeUndefined()
  })

  it('con football-data caído lo dice, sin cuenta regresiva', () => {
    const error = new ErrorApi(
      503,
      'football_data_no_disponible',
      'No pudimos conectarnos con football-data.org.',
    )
    const alerta = montar(AlertaError, { error })

    expect(alerta.text()).toContain('football-data.org no está disponible')
    expect(alerta.text()).not.toContain('Reintentaremos')
  })
})

describe('EstadoAsincrono', () => {
  const contenido = { default: '<p>datos cargados</p>' }

  it('mientras carga muestra el esqueleto y no el contenido', () => {
    const estado = montar(EstadoAsincrono, { cargando: true, error: null }, contenido)

    expect(estado.find('.v-skeleton-loader').exists()).toBe(true)
    expect(estado.text()).not.toContain('datos cargados')
  })

  it('sin datos muestra el estado vacío con su pista', () => {
    const estado = montar(
      EstadoAsincrono,
      {
        cargando: false,
        error: null,
        vacio: true,
        tituloVacio: 'Todavía no hay equipos',
        textoVacio: 'Importa una liga.',
      },
      contenido,
    )

    expect(estado.text()).toContain('Todavía no hay equipos')
    expect(estado.text()).toContain('Importa una liga.')
    expect(estado.text()).not.toContain('datos cargados')
  })

  it('con un error muestra la alerta y deja reintentar', async () => {
    const error = new ErrorApi(404, 'no_encontrado', 'El equipo solicitado no existe.')
    const estado = montar(EstadoAsincrono, { cargando: false, error }, contenido)

    expect(estado.text()).toContain('El equipo solicitado no existe.')
    await estado.findComponent(AlertaError).vm.$emit('reintentar')
    expect(estado.emitted('reintentar')).toHaveLength(1)
  })

  it('con datos muestra el contenido', () => {
    const estado = montar(EstadoAsincrono, { cargando: false, error: null }, contenido)

    expect(estado.text()).toContain('datos cargados')
  })
})
