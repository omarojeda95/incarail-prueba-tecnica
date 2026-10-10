<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useAbility } from '@casl/vue'
import type { AppAbility } from '@/ability'
import { accessApi } from '@/api/access'
import { toApiError } from '@/api/errors'
import { useAsync } from '@/composables/useAsync'
import { useDebouncedRef } from '@/composables/useDebounced'
import { useAuthStore } from '@/stores/auth'
import { useNotifyStore } from '@/stores/notify'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ErrorAlert from '@/components/ErrorAlert.vue'
import PageHeader from '@/components/PageHeader.vue'
import UserFormDialog from './UserFormDialog.vue'
import type { User } from '@/types/api'

const { can } = useAbility<AppAbility>()
const auth = useAuthStore()
const notify = useNotifyStore()

const search = useDebouncedRef('')
const options = ref({ page: 1, itemsPerPage: 20 })

const users = useAsync(() =>
  accessApi.users({
    search: search.value || undefined,
    page: options.value.page,
    per_page: options.value.itemsPerPage,
  }),
)
const roles = useAsync(() => accessApi.roles())

const headers = [
  { title: 'Nombre', key: 'name', sortable: false },
  { title: 'Correo', key: 'email', sortable: false },
  { title: 'Roles', key: 'roles', sortable: false },
  { title: 'Estado', key: 'is_active', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
]

watch(search, () => {
  options.value = { ...options.value, page: 1 }
  users.run()
})
onMounted(() => {
  if (can('read', 'Role')) roles.run()
})

function onOptions(next: { page: number; itemsPerPage: number }) {
  options.value = { page: next.page, itemsPerPage: next.itemsPerPage }
  users.run()
}

const formOpen = ref(false)
const editing = ref<User | null>(null)
function openForm(user: User | null) {
  editing.value = user
  formOpen.value = true
}
function onSaved(user: User) {
  notify.success(
    editing.value ? `Usuario ${user.name} actualizado.` : `Usuario ${user.name} creado.`,
  )
  users.run()
}

const toggling = ref<User | null>(null)
const toggleBusy = ref(false)
async function confirmToggle() {
  const user = toggling.value
  if (!user) return
  toggleBusy.value = true
  try {
    if (user.is_active) await accessApi.deactivateUser(user.id)
    else await accessApi.activateUser(user.id)
    notify.success(
      user.is_active
        ? `${user.name} fue desactivado y sus sesiones se cerraron.`
        : `${user.name} fue reactivado.`,
    )
    users.run()
  } catch (e) {
    notify.error(toApiError(e).message)
  } finally {
    toggleBusy.value = false
    toggling.value = null
  }
}
</script>

<template>
  <PageHeader title="Usuarios" subtitle="Crea, edita, asigna roles y desactiva usuarios.">
    <template #actions>
      <v-btn
        v-if="can('create', 'User')"
        color="primary"
        prepend-icon="mdi-account-plus"
        @click="openForm(null)"
      >
        Nuevo usuario
      </v-btn>
    </template>
  </PageHeader>

  <v-text-field
    :model-value="search"
    label="Buscar por nombre o correo"
    prepend-inner-icon="mdi-magnify"
    clearable
    class="mb-4"
    style="max-width: 480px"
    @update:model-value="search = $event ?? ''"
  />

  <ErrorAlert v-if="users.error.value" :error="users.error.value" retryable @retry="users.run()" />

  <v-card>
    <v-data-table-server
      :headers="headers"
      :items="users.data.value?.data ?? []"
      :items-length="users.data.value?.meta.total ?? 0"
      :loading="users.loading.value"
      :page="options.page"
      :items-per-page="options.itemsPerPage"
      mobile-breakpoint="md"
      no-data-text="No hay usuarios con esa búsqueda."
      @update:options="onOptions"
    >
      <template #[`item.roles`]="{ item }">
        <v-chip
          v-for="role in item.roles"
          :key="role"
          size="small"
          class="mr-1"
          color="secondary"
          >{{ role }}</v-chip
        >
        <span v-if="!item.roles?.length" class="text-medium-emphasis">Sin rol</span>
      </template>
      <template #[`item.is_active`]="{ item }">
        <v-chip :color="item.is_active ? 'success' : 'default'" size="small" variant="tonal">
          {{ item.is_active ? 'Activo' : 'Inactivo' }}
        </v-chip>
      </template>
      <template #[`item.actions`]="{ item }">
        <v-btn
          v-if="can('update', 'User')"
          icon="mdi-pencil"
          variant="text"
          size="small"
          aria-label="Editar"
          @click="openForm(item)"
        />
        <v-btn
          v-if="can('deactivate', 'User') && item.id !== auth.user?.id"
          :icon="item.is_active ? 'mdi-account-off-outline' : 'mdi-account-check-outline'"
          :color="item.is_active ? 'error' : 'success'"
          variant="text"
          size="small"
          :aria-label="item.is_active ? 'Desactivar' : 'Activar'"
          @click="toggling = item"
        />
      </template>
    </v-data-table-server>
  </v-card>

  <UserFormDialog
    v-model="formOpen"
    :user="editing"
    :roles="(roles.data.value ?? []).map((role) => role.name)"
    @saved="onSaved"
  />

  <ConfirmDialog
    :model-value="toggling !== null"
    :title="toggling?.is_active ? 'Desactivar usuario' : 'Activar usuario'"
    :text="
      toggling?.is_active
        ? `${toggling?.name} no podrá iniciar sesión y sus sesiones abiertas se cerrarán de inmediato.`
        : `${toggling?.name} podrá volver a iniciar sesión.`
    "
    :confirm-text="toggling?.is_active ? 'Desactivar' : 'Activar'"
    :color="toggling?.is_active ? 'error' : 'success'"
    :loading="toggleBusy"
    @update:model-value="!$event && (toggling = null)"
    @confirm="confirmToggle"
  />
</template>
