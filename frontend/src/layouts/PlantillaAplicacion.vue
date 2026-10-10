<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAbility } from '@casl/vue'
import type { HabilidadesApp, Sujeto } from '@/habilidades'
import { useSesionStore } from '@/stores/sesion'

const sesion = useSesionStore()
const router = useRouter()
const { mobile } = useDisplay()
const { can } = useAbility<HabilidadesApp>()

const menuAbierto = ref<boolean | null>(null)

interface ElementoMenu {
  titulo: string
  icono: string
  ruta: string
  sujeto: Sujeto
}

const secciones: { titulo: string; elementos: ElementoMenu[] }[] = [
  {
    titulo: 'Fútbol',
    elementos: [
      { titulo: 'Ligas', icono: 'mdi-trophy-outline', ruta: '/ligas', sujeto: 'Liga' },
      { titulo: 'Equipos', icono: 'mdi-shield-half-full', ruta: '/equipos', sujeto: 'Equipo' },
      {
        titulo: 'Jugadores',
        icono: 'mdi-account-group-outline',
        ruta: '/jugadores',
        sujeto: 'Jugador',
      },
    ],
  },
  {
    titulo: 'Administración',
    elementos: [
      {
        titulo: 'Usuarios',
        icono: 'mdi-account-cog-outline',
        ruta: '/admin/usuarios',
        sujeto: 'Usuario',
      },
      {
        titulo: 'Roles y permisos',
        icono: 'mdi-shield-key-outline',
        ruta: '/admin/roles',
        sujeto: 'Rol',
      },
    ],
  },
]

// Solo se muestra lo que las habilidades del usuario permiten (la API lo exige igual).
const seccionesVisibles = computed(() =>
  secciones
    .map((seccion) => ({
      ...seccion,
      elementos: seccion.elementos.filter((e) => can('ver', e.sujeto)),
    }))
    .filter((seccion) => seccion.elementos.length > 0),
)

const cerrando = ref(false)
async function cerrarSesion() {
  cerrando.value = true
  await sesion.cerrarSesion().catch(() => undefined)
  cerrando.value = false
  router.push({ name: 'iniciar-sesion' })
}
</script>

<template>
  <v-navigation-drawer v-model="menuAbierto" :temporary="mobile" :permanent="!mobile" width="260">
    <div class="d-flex align-center ga-3 px-4 py-5">
      <v-icon icon="mdi-soccer" color="secondary" size="32" />
      <div>
        <div class="text-subtitle-1 font-weight-bold">Inca Rail</div>
        <div class="text-caption text-medium-emphasis">Plataforma de fútbol</div>
      </div>
    </div>
    <v-divider />

    <v-list nav density="comfortable" color="primary">
      <template v-for="seccion in seccionesVisibles" :key="seccion.titulo">
        <v-list-subheader>{{ seccion.titulo }}</v-list-subheader>
        <v-list-item
          v-for="elemento in seccion.elementos"
          :key="elemento.ruta"
          :to="elemento.ruta"
          :prepend-icon="elemento.icono"
          :title="elemento.titulo"
        />
      </template>
    </v-list>
  </v-navigation-drawer>

  <v-app-bar flat border="b" color="surface">
    <v-app-bar-nav-icon v-if="mobile" aria-label="Abrir menú" @click="menuAbierto = !menuAbierto" />
    <v-app-bar-title class="text-body-1 text-medium-emphasis">
      {{ $route.meta.titulo }}
    </v-app-bar-title>

    <v-menu location="bottom end">
      <template #activator="{ props }">
        <v-btn v-bind="props" variant="text" class="text-none mr-2" append-icon="mdi-chevron-down">
          <v-avatar color="primary" size="28" class="mr-sm-2">
            <span class="text-caption">{{ sesion.usuario?.nombre.charAt(0) }}</span>
          </v-avatar>
          <span class="d-none d-sm-inline">{{ sesion.usuario?.nombre }}</span>
        </v-btn>
      </template>
      <v-card min-width="240">
        <v-card-text>
          <div class="font-weight-medium">{{ sesion.usuario?.nombre }}</div>
          <div class="text-caption text-medium-emphasis mb-2">{{ sesion.usuario?.correo }}</div>
          <v-chip
            v-for="rol in sesion.usuario?.roles"
            :key="rol"
            size="small"
            class="mr-1"
            color="secondary"
          >
            {{ rol }}
          </v-chip>
        </v-card-text>
        <v-divider />
        <v-list density="compact">
          <v-list-item
            prepend-icon="mdi-logout"
            title="Cerrar sesión"
            :disabled="cerrando"
            @click="cerrarSesion"
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
