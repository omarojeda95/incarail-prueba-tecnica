import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import router from '@/router'
import { aplicarReglas } from '@/habilidades'
import { useSesionStore } from '@/stores/sesion'
import type { ReglaCasl } from '@/tipos/api'

/** Deja una sesión ya restaurada con las reglas CASL indicadas (sin llamar a la API). */
function iniciarSesionCon(reglas: ReglaCasl[]) {
  const sesion = useSesionStore()
  sesion.token = 'token-de-prueba'
  sesion.usuario = {
    id: 1,
    nombre: 'Prueba',
    correo: 'prueba@incarail.test',
    activo: true,
    desactivado_en: null,
    creado_en: null,
  }
  sesion.lista = true
  aplicarReglas(reglas)
}

const consulta: ReglaCasl[] = [
  { action: 'ver', subject: 'Liga' },
  { action: 'ver', subject: 'Equipo' },
  { action: 'ver', subject: 'Jugador' },
]

describe('guardia del router', () => {
  beforeEach(async () => {
    localStorage.clear()
    setActivePinia(createPinia())
    useSesionStore().lista = true
    aplicarReglas([])
    await router.push('/iniciar-sesion')
  })

  it('sin sesión manda al inicio de sesión y recuerda a dónde iba', async () => {
    await router.push('/equipos')

    expect(router.currentRoute.value.name).toBe('iniciar-sesion')
    expect(router.currentRoute.value.query.volver).toBe('/equipos')
  })

  it('sin el permiso de la página muestra «Sin permiso», aunque la SPA no tenga el botón', async () => {
    iniciarSesionCon(consulta)

    await router.push('/admin/usuarios')

    expect(router.currentRoute.value.name).toBe('sin-permiso')
    expect(router.currentRoute.value.query.desde).toBe('/admin/usuarios')
  })

  it('con el permiso deja entrar', async () => {
    iniciarSesionCon([...consulta, { action: 'ver', subject: 'Usuario' }])

    await router.push('/admin/usuarios')

    expect(router.currentRoute.value.name).toBe('usuarios')
  })

  it('con sesión, el inicio de sesión lleva a la primera página que se puede ver', async () => {
    iniciarSesionCon([{ action: 'ver', subject: 'Jugador' }])

    // (con otra query: navegar a la misma ruta en la que ya está no dispara el guardia)
    await router.push({ name: 'iniciar-sesion', query: { volver: '/' } })

    expect(router.currentRoute.value.name).toBe('jugadores')
  })
})
