<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { accessApi, type UserPayload } from '@/api/access'
import { ApiError, toApiError } from '@/api/errors'
import type { User } from '@/types/api'

const props = defineProps<{ user: User | null; roles: string[] }>()
const open = defineModel<boolean>({ required: true })
const emit = defineEmits<{ saved: [user: User] }>()

const form = ref<UserPayload>({ name: '', email: '', password: '', roles: [] })
const saving = ref(false)
const error = ref<ApiError | null>(null)
const isEdit = computed(() => props.user !== null)

watch(open, (isOpen) => {
  if (!isOpen) return
  error.value = null
  form.value = {
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    roles: [...(props.user?.roles ?? [])],
  }
})

async function save() {
  saving.value = true
  error.value = null
  try {
    const payload = { ...form.value, password: form.value.password || null }
    const saved = props.user
      ? await accessApi.updateUser(props.user.id, payload)
      : await accessApi.createUser(payload)
    emit('saved', saved)
    open.value = false
  } catch (e) {
    error.value = toApiError(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-dialog v-model="open" max-width="520" persistent>
    <v-card :title="isEdit ? 'Editar usuario' : 'Nuevo usuario'">
      <v-form @submit.prevent="save">
        <v-card-text class="d-flex flex-column ga-4">
          <v-alert
            v-if="error && error.status !== 422"
            type="error"
            variant="tonal"
            density="compact"
          >
            {{ error.message }}
          </v-alert>
          <v-text-field
            v-model="form.name"
            label="Nombre"
            :error-messages="error?.field('name')"
            autofocus
          />
          <v-text-field
            v-model="form.email"
            label="Correo"
            type="email"
            :error-messages="error?.field('email')"
          />
          <v-text-field
            v-model="form.password"
            label="Contraseña"
            type="password"
            autocomplete="new-password"
            :hint="
              isEdit
                ? 'Déjala vacía para mantener la actual'
                : 'Mínimo 8 caracteres, con letras y números'
            "
            persistent-hint
            :error-messages="error?.field('password')"
          />
          <v-select
            v-model="form.roles"
            :items="roles"
            label="Roles"
            multiple
            chips
            closable-chips
            :error-messages="error?.field('roles') ?? error?.field('roles.0')"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="open = false">Cancelar</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="saving">Guardar</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>
