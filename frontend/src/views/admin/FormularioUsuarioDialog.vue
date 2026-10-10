<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { apiAcceso, type DatosUsuario } from '@/api/acceso'
import { ErrorApi, aErrorApi } from '@/api/errores'
import type { Usuario } from '@/tipos/api'

const props = defineProps<{ usuario: Usuario | null; roles: string[] }>()
const abierto = defineModel<boolean>({ required: true })
const emit = defineEmits<{ guardado: [usuario: Usuario] }>()

const formulario = ref<DatosUsuario>({ nombre: '', correo: '', contrasena: '', roles: [] })
const guardando = ref(false)
const error = ref<ErrorApi | null>(null)
const esEdicion = computed(() => props.usuario !== null)

watch(abierto, (estaAbierto) => {
  if (!estaAbierto) return
  error.value = null
  formulario.value = {
    nombre: props.usuario?.nombre ?? '',
    correo: props.usuario?.correo ?? '',
    contrasena: '',
    roles: [...(props.usuario?.roles ?? [])],
  }
})

async function guardar() {
  guardando.value = true
  error.value = null
  try {
    const datos = { ...formulario.value, contrasena: formulario.value.contrasena || null }
    const guardado = props.usuario
      ? await apiAcceso.editarUsuario(props.usuario.id, datos)
      : await apiAcceso.crearUsuario(datos)
    emit('guardado', guardado)
    abierto.value = false
  } catch (e) {
    error.value = aErrorApi(e)
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <v-dialog v-model="abierto" max-width="520" persistent>
    <v-card :title="esEdicion ? 'Editar usuario' : 'Nuevo usuario'">
      <v-form @submit.prevent="guardar">
        <v-card-text class="d-flex flex-column ga-4">
          <v-alert
            v-if="error && error.estado !== 422"
            type="error"
            variant="tonal"
            density="compact"
          >
            {{ error.message }}
          </v-alert>
          <v-text-field
            v-model="formulario.nombre"
            label="Nombre"
            :error-messages="error?.campo('nombre')"
            autofocus
          />
          <v-text-field
            v-model="formulario.correo"
            label="Correo"
            type="email"
            :error-messages="error?.campo('correo')"
          />
          <v-text-field
            v-model="formulario.contrasena"
            label="Contraseña"
            type="password"
            autocomplete="new-password"
            :hint="
              esEdicion
                ? 'Déjala vacía para mantener la actual'
                : 'Mínimo 8 caracteres, con letras y números'
            "
            persistent-hint
            :error-messages="error?.campo('contrasena')"
          />
          <v-select
            v-model="formulario.roles"
            :items="roles"
            label="Roles"
            multiple
            chips
            closable-chips
            :error-messages="error?.campo('roles') ?? error?.campo('roles.0')"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="abierto = false">Cancelar</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="guardando">Guardar</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>
