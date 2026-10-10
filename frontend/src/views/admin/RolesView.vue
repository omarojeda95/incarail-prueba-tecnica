<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAbility } from '@casl/vue'
import type { AppAbility } from '@/ability'
import { accessApi } from '@/api/access'
import { ApiError, toApiError } from '@/api/errors'
import { useAsync } from '@/composables/useAsync'
import { useNotifyStore } from '@/stores/notify'
import AsyncState from '@/components/AsyncState.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageHeader from '@/components/PageHeader.vue'
import type { Permission, Role } from '@/types/api'

const { can } = useAbility<AppAbility>()
const notify = useNotifyStore()

const roles = useAsync(() => accessApi.roles())
const permissions = useAsync(() => accessApi.permissions())
onMounted(() => Promise.all([roles.run(), permissions.run()]))

const SUBJECTS: Record<string, string> = {
  Competition: 'Ligas',
  Team: 'Equipos',
  Player: 'Jugadores',
  User: 'Usuarios',
  Role: 'Roles',
}

/** Permission catalog grouped by CASL subject, to build the editor. */
const groups = computed(() => {
  const map = new Map<string, Permission[]>()
  for (const permission of permissions.data.value ?? []) {
    map.set(permission.subject, [...(map.get(permission.subject) ?? []), permission])
  }
  return [...map.entries()].map(([subject, items]) => ({
    subject,
    label: SUBJECTS[subject] ?? subject,
    items,
  }))
})
const describe = (name: string) =>
  permissions.data.value?.find((p) => p.name === name)?.description ?? name

// --- editor
const editorOpen = ref(false)
const editing = ref<Role | null>(null)
const form = ref({ name: '', permissions: [] as string[] })
const saving = ref(false)
const formError = ref<ApiError | null>(null)

function openEditor(role: Role | null) {
  editing.value = role
  form.value = { name: role?.name ?? '', permissions: [...(role?.permissions ?? [])] }
  formError.value = null
  editorOpen.value = true
}

async function save() {
  saving.value = true
  formError.value = null
  try {
    if (editing.value) await accessApi.updateRole(editing.value.id, form.value)
    else await accessApi.createRole(form.value)
    notify.success(`Rol ${form.value.name} guardado.`)
    editorOpen.value = false
    roles.run()
  } catch (e) {
    formError.value = toApiError(e)
  } finally {
    saving.value = false
  }
}

// --- delete
const deleting = ref<Role | null>(null)
const deleteBusy = ref(false)
async function confirmDelete() {
  if (!deleting.value) return
  deleteBusy.value = true
  try {
    await accessApi.deleteRole(deleting.value.id)
    notify.success(`Rol ${deleting.value.name} eliminado.`)
    roles.run()
  } catch (e) {
    notify.error(toApiError(e).message)
  } finally {
    deleteBusy.value = false
    deleting.value = null
  }
}
</script>

<template>
  <PageHeader
    title="Roles y permisos"
    subtitle="Cada rol agrupa permisos. El servidor valida cada permiso; la interfaz solo se adapta a ellos."
  >
    <template #actions>
      <v-btn
        v-if="can('manage', 'Role')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="openEditor(null)"
      >
        Nuevo rol
      </v-btn>
    </template>
  </PageHeader>

  <AsyncState
    :loading="roles.loading.value"
    :error="roles.error.value ?? permissions.error.value"
    :empty="roles.data.value?.length === 0"
    @retry="roles.run()"
  >
    <v-row>
      <v-col v-for="role in roles.data.value" :key="role.id" cols="12" md="6" xl="4">
        <v-card height="100%">
          <v-card-item>
            <v-card-title class="d-flex align-center ga-2">
              {{ role.name }}
              <v-chip v-if="role.protected" size="x-small" prepend-icon="mdi-lock">sistema</v-chip>
            </v-card-title>
            <v-card-subtitle
              >{{ role.users_count }} usuario(s) ·
              {{ role.permissions.length }} permiso(s)</v-card-subtitle
            >
            <template v-if="can('manage', 'Role') && !role.protected" #append>
              <v-btn
                icon="mdi-pencil"
                variant="text"
                size="small"
                aria-label="Editar rol"
                @click="openEditor(role)"
              />
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                color="error"
                aria-label="Eliminar rol"
                @click="deleting = role"
              />
            </template>
          </v-card-item>
          <v-card-text>
            <v-chip
              v-for="permission in role.permissions"
              :key="permission"
              size="small"
              variant="tonal"
              color="primary"
              class="mr-1 mb-1"
              :title="describe(permission)"
            >
              {{ permission }}
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </AsyncState>

  <v-dialog v-model="editorOpen" max-width="640" scrollable>
    <v-card :title="editing ? `Editar rol ${editing.name}` : 'Nuevo rol'">
      <v-card-text>
        <v-alert
          v-if="formError && formError.status !== 422"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ formError.message }}
        </v-alert>
        <v-text-field
          v-model="form.name"
          label="Nombre del rol"
          hint="Minúsculas, números, guiones"
          persistent-hint
          :error-messages="formError?.field('name')"
          class="mb-4"
        />
        <div v-for="group in groups" :key="group.subject" class="mb-3">
          <div class="text-subtitle-2 mb-1">{{ group.label }}</div>
          <v-checkbox
            v-for="permission in group.items"
            :key="permission.name"
            v-model="form.permissions"
            :value="permission.name"
            density="compact"
            hide-details
          >
            <template #label>
              <div>
                <div>{{ permission.description }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ permission.name }} · CASL: {{ permission.action }} / {{ permission.subject }}
                </div>
              </div>
            </template>
          </v-checkbox>
        </div>
        <div v-if="formError?.field('permissions')" class="text-error text-caption">
          {{ formError.field('permissions') }}
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="editorOpen = false">Cancelar</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">Guardar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <ConfirmDialog
    :model-value="deleting !== null"
    title="Eliminar rol"
    :text="`¿Eliminar el rol ${deleting?.name}? Esta acción no se puede deshacer.`"
    confirm-text="Eliminar"
    color="error"
    :loading="deleteBusy"
    @update:model-value="!$event && (deleting = null)"
    @confirm="confirmDelete"
  />
</template>
