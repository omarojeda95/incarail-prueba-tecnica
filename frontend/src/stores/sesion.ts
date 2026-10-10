import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { apiAutenticacion } from '@/api/autenticacion'
import { CLAVE_TOKEN } from '@/api/cliente'
import { aplicarReglas } from '@/habilidades'
import type { Sesion, Usuario } from '@/tipos/api'

export const useSesionStore = defineStore('sesion', () => {
  const token = ref<string | null>(localStorage.getItem(CLAVE_TOKEN))
  const usuario = ref<Usuario | null>(null)
  /** True cuando ya sabemos si el token guardado sigue siendo válido. */
  const lista = ref(false)

  const autenticado = computed(() => token.value !== null && usuario.value !== null)

  function establecer(sesion: Sesion): void {
    usuario.value = sesion.usuario
    aplicarReglas(sesion.habilidades)
  }

  async function iniciarSesion(correo: string, contrasena: string): Promise<void> {
    const respuesta = await apiAutenticacion.iniciarSesion(correo, contrasena)
    token.value = respuesta.token_acceso
    localStorage.setItem(CLAVE_TOKEN, respuesta.token_acceso)
    establecer(respuesta)
  }

  /** Restaura la sesión al recargar: la API devuelve habilidades actualizadas. */
  async function restaurar(): Promise<void> {
    if (lista.value) return
    try {
      if (token.value) establecer(await apiAutenticacion.sesion())
    } catch {
      limpiar()
    } finally {
      lista.value = true
    }
  }

  async function cerrarSesion(): Promise<void> {
    try {
      await apiAutenticacion.cerrarSesion()
    } finally {
      limpiar()
    }
  }

  function limpiar(): void {
    token.value = null
    usuario.value = null
    localStorage.removeItem(CLAVE_TOKEN)
    aplicarReglas([])
  }

  return { token, usuario, lista, autenticado, iniciarSesion, restaurar, cerrarSesion, limpiar }
})
