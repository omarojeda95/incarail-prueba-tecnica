import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '@/api/auth'
import { TOKEN_KEY } from '@/api/http'
import { applyRules } from '@/ability'
import type { Session, User } from '@/types/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const user = ref<User | null>(null)
  /** True once we know whether the stored token is still valid. */
  const ready = ref(false)

  const isAuthenticated = computed(() => token.value !== null && user.value !== null)

  function setSession(session: Session): void {
    user.value = session.user
    applyRules(session.abilities)
  }

  async function login(email: string, password: string): Promise<void> {
    const response = await authApi.login(email, password)
    token.value = response.access_token
    localStorage.setItem(TOKEN_KEY, response.access_token)
    setSession(response)
  }

  /** Restores the session after a reload: the API gives back fresh abilities. */
  async function restore(): Promise<void> {
    if (ready.value) return
    try {
      if (token.value) setSession(await authApi.me())
    } catch {
      clear()
    } finally {
      ready.value = true
    }
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      clear()
    }
  }

  function clear(): void {
    token.value = null
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
    applyRules([])
  }

  return { token, user, ready, isAuthenticated, login, restore, logout, clear }
})
