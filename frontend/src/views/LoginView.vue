<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, toApiError } from '@/api/errors'
import { homeFor } from '@/router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref<ApiError | null>(null)

const demoUsers = [
  { email: 'admin@incarail.test', role: 'admin' },
  { email: 'analista@incarail.test', role: 'analista' },
  { email: 'consulta@incarail.test', role: 'consulta' },
]

async function submit() {
  loading.value = true
  error.value = null
  try {
    await auth.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    await router.replace(redirect ?? homeFor())
  } catch (e) {
    error.value = toApiError(e)
  } finally {
    loading.value = false
  }
}

function useDemo(demoEmail: string) {
  email.value = demoEmail
  password.value = 'password'
}
</script>

<template>
  <v-main class="login-bg d-flex align-center">
    <v-container class="py-8" style="max-width: 440px">
      <div class="text-center text-white mb-6">
        <v-icon icon="mdi-soccer" size="56" color="secondary" />
        <h1 class="text-h5 font-weight-bold mt-2">Inca Rail · Fútbol</h1>
        <p class="text-body-2" style="opacity: 0.8">Ligas, equipos y jugadores en un solo lugar</p>
      </div>

      <v-card class="pa-2" elevation="8">
        <v-card-title class="text-h6">Iniciar sesión</v-card-title>
        <v-card-text>
          <v-alert
            v-if="error"
            :type="error.status === 401 || error.status === 422 ? 'error' : 'warning'"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ error.message }}
          </v-alert>

          <v-form @submit.prevent="submit">
            <v-text-field
              v-model="email"
              label="Correo"
              type="email"
              autocomplete="username"
              prepend-inner-icon="mdi-email-outline"
              :error-messages="error?.field('email')"
              class="mb-3"
              autofocus
              required
            />
            <v-text-field
              v-model="password"
              label="Contraseña"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              prepend-inner-icon="mdi-lock-outline"
              :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
              :error-messages="error?.field('password')"
              class="mb-4"
              required
              @click:append-inner="showPassword = !showPassword"
            />
            <v-btn type="submit" color="primary" size="large" block :loading="loading"
              >Ingresar</v-btn
            >
          </v-form>
        </v-card-text>

        <v-divider />
        <v-card-text>
          <div class="text-caption text-medium-emphasis mb-2">
            Usuarios de prueba (contraseña: password)
          </div>
          <div class="d-flex flex-wrap ga-2">
            <v-chip
              v-for="demo in demoUsers"
              :key="demo.email"
              size="small"
              prepend-icon="mdi-account"
              @click="useDemo(demo.email)"
            >
              {{ demo.role }}
            </v-chip>
          </div>
        </v-card-text>
      </v-card>
    </v-container>
  </v-main>
</template>

<style scoped>
.login-bg {
  background: linear-gradient(160deg, #0b3c5d 0%, #12577f 55%, #c8963e 140%);
  min-height: 100vh;
}
</style>
