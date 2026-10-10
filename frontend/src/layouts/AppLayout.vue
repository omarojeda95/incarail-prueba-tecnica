<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAbility } from '@casl/vue'
import type { AppAbility, Subject } from '@/ability'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { mobile } = useDisplay()
const { can } = useAbility<AppAbility>()

const drawer = ref<boolean | null>(null)

interface NavItem {
  title: string
  icon: string
  to: string
  subject: Subject
}

const sections: { header: string; items: NavItem[] }[] = [
  {
    header: 'Fútbol',
    items: [
      { title: 'Ligas', icon: 'mdi-trophy-outline', to: '/competitions', subject: 'Competition' },
      { title: 'Equipos', icon: 'mdi-shield-half-full', to: '/teams', subject: 'Team' },
      { title: 'Jugadores', icon: 'mdi-account-group-outline', to: '/players', subject: 'Player' },
    ],
  },
  {
    header: 'Administración',
    items: [
      { title: 'Usuarios', icon: 'mdi-account-cog-outline', to: '/admin/users', subject: 'User' },
      {
        title: 'Roles y permisos',
        icon: 'mdi-shield-key-outline',
        to: '/admin/roles',
        subject: 'Role',
      },
    ],
  },
]

// Only what the user's abilities allow is shown (the API enforces it anyway).
const visibleSections = computed(() =>
  sections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => can('read', item.subject)),
    }))
    .filter((section) => section.items.length > 0),
)

const loggingOut = ref(false)
async function logout() {
  loggingOut.value = true
  await auth.logout().catch(() => undefined)
  loggingOut.value = false
  router.push({ name: 'login' })
}
</script>

<template>
  <v-navigation-drawer v-model="drawer" :temporary="mobile" :permanent="!mobile" width="260">
    <div class="d-flex align-center ga-3 px-4 py-5">
      <v-icon icon="mdi-soccer" color="secondary" size="32" />
      <div>
        <div class="text-subtitle-1 font-weight-bold">Inca Rail</div>
        <div class="text-caption text-medium-emphasis">Plataforma de fútbol</div>
      </div>
    </div>
    <v-divider />

    <v-list nav density="comfortable" color="primary">
      <template v-for="section in visibleSections" :key="section.header">
        <v-list-subheader>{{ section.header }}</v-list-subheader>
        <v-list-item
          v-for="item in section.items"
          :key="item.to"
          :to="item.to"
          :prepend-icon="item.icon"
          :title="item.title"
        />
      </template>
    </v-list>
  </v-navigation-drawer>

  <v-app-bar flat border="b" color="surface">
    <v-app-bar-nav-icon v-if="mobile" aria-label="Abrir menú" @click="drawer = !drawer" />
    <v-app-bar-title class="text-body-1 text-medium-emphasis">
      {{ $route.meta.title }}
    </v-app-bar-title>

    <v-menu location="bottom end">
      <template #activator="{ props }">
        <v-btn v-bind="props" variant="text" class="text-none mr-2" append-icon="mdi-chevron-down">
          <v-avatar color="primary" size="28" class="mr-sm-2">
            <span class="text-caption">{{ auth.user?.name.charAt(0) }}</span>
          </v-avatar>
          <span class="d-none d-sm-inline">{{ auth.user?.name }}</span>
        </v-btn>
      </template>
      <v-card min-width="240">
        <v-card-text>
          <div class="font-weight-medium">{{ auth.user?.name }}</div>
          <div class="text-caption text-medium-emphasis mb-2">{{ auth.user?.email }}</div>
          <v-chip
            v-for="role in auth.user?.roles"
            :key="role"
            size="small"
            class="mr-1"
            color="secondary"
          >
            {{ role }}
          </v-chip>
        </v-card-text>
        <v-divider />
        <v-list density="compact">
          <v-list-item
            prepend-icon="mdi-logout"
            title="Cerrar sesión"
            :disabled="loggingOut"
            @click="logout"
          />
        </v-list>
      </v-card>
    </v-menu>
  </v-app-bar>

  <v-main>
    <v-container class="py-6 px-4 px-md-8" style="max-width: 1400px">
      <RouterView />
    </v-container>
  </v-main>
</template>
