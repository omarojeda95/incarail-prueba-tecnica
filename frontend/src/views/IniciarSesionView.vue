<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ErrorApi, aErrorApi } from '@/api/errores'
import { paginaInicial } from '@/router'
import { useSesionStore } from '@/stores/sesion'

const sesion = useSesionStore()
const router = useRouter()
const ruta = useRoute()

const correo = ref('')
const contrasena = ref('')
const mostrarContrasena = ref(false)
const cargando = ref(false)
const error = ref<ErrorApi | null>(null)

// Solo en la demo: un build sin la variable no muestra credenciales en el login.
const mostrarUsuariosPrueba = import.meta.env.VITE_MOSTRAR_USUARIOS_PRUEBA === 'true'
const usuariosPrueba = [
  { correo: 'admin@incarail.test', rol: 'admin' },
  { correo: 'analista@incarail.test', rol: 'analista' },
  { correo: 'consulta@incarail.test', rol: 'consulta' },
]

async function enviar() {
  cargando.value = true
  error.value = null
  try {
    await sesion.iniciarSesion(correo.value, contrasena.value)
    const volver = typeof ruta.query.volver === 'string' ? ruta.query.volver : null
    await router.replace(volver ?? paginaInicial())
  } catch (e) {
    error.value = aErrorApi(e)
  } finally {
    cargando.value = false
  }
}

function usarPrueba(correoPrueba: string) {
  correo.value = correoPrueba
  contrasena.value = 'password'
}
</script>

<template>
  <v-main class="fondo d-flex align-center">
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
            :type="error.estado === 401 || error.estado === 422 ? 'error' : 'warning'"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ error.message }}
          </v-alert>

          <v-form @submit.prevent="enviar">
            <v-text-field
              v-model="correo"
              label="Correo"
              type="email"
              autocomplete="username"
              prepend-inner-icon="mdi-email-outline"
              :error-messages="error?.campo('correo')"
              class="mb-3"
              autofocus
              required
            />
            <v-text-field
              v-model="contrasena"
              label="Contraseña"
              :type="mostrarContrasena ? 'text' : 'password'"
              autocomplete="current-password"
              prepend-inner-icon="mdi-lock-outline"
              :append-inner-icon="mostrarContrasena ? 'mdi-eye-off' : 'mdi-eye'"
              :error-messages="error?.campo('contrasena')"
              class="mb-4"
              required
              @click:append-inner="mostrarContrasena = !mostrarContrasena"
            />
            <v-btn type="submit" color="primary" size="large" block :loading="cargando"
              >Ingresar</v-btn
            >
          </v-form>
        </v-card-text>

        <v-divider v-if="mostrarUsuariosPrueba" />
        <v-card-text v-if="mostrarUsuariosPrueba">
          <div class="text-caption text-medium-emphasis mb-2">
            Usuarios de prueba (contraseña: password)
          </div>
          <div class="d-flex flex-wrap ga-2">
            <v-chip
              v-for="prueba in usuariosPrueba"
              :key="prueba.correo"
              size="small"
              prepend-icon="mdi-account"
              @click="usarPrueba(prueba.correo)"
            >
              {{ prueba.rol }}
            </v-chip>
          </div>
        </v-card-text>
      </v-card>
    </v-container>
  </v-main>
</template>

<style scoped>
.fondo {
  background: linear-gradient(160deg, #0b3c5d 0%, #12577f 55%, #c8963e 140%);
  min-height: 100vh;
}
</style>
