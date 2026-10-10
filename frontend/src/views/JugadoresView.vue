<script setup lang="ts">
import { ref, watch } from 'vue'
import { apiFutbol } from '@/api/futbol'
import { useAsincrono } from '@/composables/useAsincrono'
import { useRefConRetraso } from '@/composables/useRefConRetraso'
import AlertaError from '@/components/AlertaError.vue'
import EncabezadoPagina from '@/components/EncabezadoPagina.vue'
import AvatarEscudo from '@/components/AvatarEscudo.vue'
import { etiquetaPosicion } from '@/utilidades/formato'

interface Orden {
  key: string
  order?: boolean | 'asc' | 'desc'
}

const buscar = useRefConRetraso('')
const posicion = ref<string | null>(null)
const equipoId = ref<number | null>(null)
const buscarEquipo = useRefConRetraso('')

const opciones = ref({ page: 1, itemsPerPage: 25, sortBy: [] as Orden[] })

const columnas = [
  { title: '#', key: 'numero_camiseta', width: 64 },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Posición', key: 'posicion' },
  { title: 'Equipo', key: 'equipo', sortable: false },
  { title: 'Nacionalidad', key: 'nacionalidad' },
  { title: 'Edad', key: 'fecha_nacimiento', align: 'end' as const },
]

const jugadores = useAsincrono(() => {
  const orden = opciones.value.sortBy[0]
  return apiFutbol.jugadores({
    buscar: buscar.value,
    posicion: posicion.value ?? undefined,
    equipo_id: equipoId.value ?? undefined,
    page: opciones.value.page,
    por_pagina: opciones.value.itemsPerPage,
    orden: orden?.key,
    direccion: orden?.order === 'desc' ? 'desc' : 'asc',
  })
})

const equipos = useAsincrono(() =>
  apiFutbol.equipos({ buscar: buscarEquipo.value, por_pagina: 20 }),
)

watch([buscar, posicion, equipoId], () => {
  if (opciones.value.page !== 1) opciones.value.page = 1
  else jugadores.ejecutar()
})
watch(buscarEquipo, () => equipos.ejecutar(), { immediate: true })

function alCambiarOpciones(nuevas: typeof opciones.value) {
  opciones.value = nuevas
  jugadores.ejecutar()
}
</script>

<template>
  <EncabezadoPagina
    titulo="Jugadores"
    subtitulo="Todos los jugadores guardados al importar ligas."
  />

  <v-card class="mb-4 pa-4" variant="flat" border>
    <v-row dense>
      <v-col cols="12" md="5">
        <v-text-field
          :model-value="buscar"
          label="Buscar jugador"
          prepend-inner-icon="mdi-magnify"
          clearable
          @update:model-value="buscar = $event ?? ''"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-select
          v-model="posicion"
          :items="
            (jugadores.datos.value?.filtros.posiciones ?? []).map((p) => ({
              title: etiquetaPosicion(p),
              value: p,
            }))
          "
          label="Posición"
          clearable
        />
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-autocomplete
          v-model="equipoId"
          :items="equipos.datos.value?.data ?? []"
          :loading="equipos.cargando.value"
          item-title="nombre"
          item-value="id"
          label="Equipo"
          no-filter
          clearable
          @update:search="buscarEquipo = $event ?? ''"
        />
      </v-col>
    </v-row>
  </v-card>

  <AlertaError
    v-if="jugadores.error.value"
    :error="jugadores.error.value"
    reintentable
    @reintentar="jugadores.ejecutar()"
  />

  <v-card>
    <v-data-table-server
      :headers="columnas"
      :items="jugadores.datos.value?.data ?? []"
      :items-length="jugadores.datos.value?.meta.total ?? 0"
      :loading="jugadores.cargando.value"
      :page="opciones.page"
      :items-per-page="opciones.itemsPerPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="opciones.sortBy"
      mobile-breakpoint="sm"
      loading-text="Cargando jugadores…"
      no-data-text="No hay jugadores guardados con esos filtros. Importa una liga para ver jugadores aquí."
      @update:options="alCambiarOpciones"
    >
      <template #[`item.numero_camiseta`]="{ item }">
        <v-avatar size="30" color="primary" variant="tonal">
          <span class="text-caption font-weight-bold">{{ item.numero_camiseta ?? '–' }}</span>
        </v-avatar>
      </template>
      <template #[`item.posicion`]="{ item }">{{ etiquetaPosicion(item.posicion) }}</template>
      <template #[`item.equipo`]="{ item }">
        <RouterLink
          v-if="item.equipo"
          :to="`/equipos/${item.equipo.id}`"
          class="d-inline-flex align-center ga-2 text-decoration-none"
        >
          <AvatarEscudo :src="item.equipo.escudo" :nombre="item.equipo.nombre" tamano="24" />
          {{ item.equipo.nombre_corto ?? item.equipo.nombre }}
        </RouterLink>
      </template>
      <template #[`item.fecha_nacimiento`]="{ item }">{{ item.edad ?? '—' }}</template>
    </v-data-table-server>
  </v-card>
</template>
