<script setup lang="ts">
import { ref, watch } from 'vue'
import { footballApi } from '@/api/football'
import { useAsync } from '@/composables/useAsync'
import { useDebouncedRef } from '@/composables/useDebounced'
import ErrorAlert from '@/components/ErrorAlert.vue'
import PageHeader from '@/components/PageHeader.vue'
import CrestAvatar from '@/components/CrestAvatar.vue'
import { positionLabel } from '@/utils/format'

interface SortItem {
  key: string
  order?: boolean | 'asc' | 'desc'
}

const search = useDebouncedRef('')
const position = ref<string | null>(null)
const teamId = ref<number | null>(null)
const teamSearch = useDebouncedRef('')

const options = ref({ page: 1, itemsPerPage: 25, sortBy: [] as SortItem[] })

const headers = [
  { title: '#', key: 'shirt_number', width: 64 },
  { title: 'Nombre', key: 'name' },
  { title: 'Posición', key: 'position' },
  { title: 'Equipo', key: 'team', sortable: false },
  { title: 'Nacionalidad', key: 'nationality' },
  { title: 'Edad', key: 'date_of_birth', align: 'end' as const },
]

const players = useAsync(() => {
  const sort = options.value.sortBy[0]
  return footballApi.players({
    search: search.value,
    position: position.value ?? undefined,
    team_id: teamId.value ?? undefined,
    page: options.value.page,
    per_page: options.value.itemsPerPage,
    sort: sort?.key,
    direction: sort?.order === 'desc' ? 'desc' : 'asc',
  })
})

const teams = useAsync(() => footballApi.teams({ search: teamSearch.value, per_page: 20 }))

watch([search, position, teamId], () => {
  if (options.value.page !== 1) options.value.page = 1
  else players.run()
})
watch(teamSearch, () => teams.run(), { immediate: true })

function onOptions(next: typeof options.value) {
  options.value = next
  players.run()
}
</script>

<template>
  <PageHeader title="Jugadores" subtitle="Todos los jugadores guardados al importar ligas." />

  <v-card class="mb-4 pa-4" variant="flat" border>
    <v-row dense>
      <v-col cols="12" md="5">
        <v-text-field
          :model-value="search"
          label="Buscar jugador"
          prepend-inner-icon="mdi-magnify"
          clearable
          @update:model-value="search = $event ?? ''"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-select
          v-model="position"
          :items="
            (players.data.value?.filters.positions ?? []).map((p) => ({
              title: positionLabel(p),
              value: p,
            }))
          "
          label="Posición"
          clearable
        />
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-autocomplete
          v-model="teamId"
          :items="teams.data.value?.data ?? []"
          :loading="teams.loading.value"
          item-title="name"
          item-value="id"
          label="Equipo"
          no-filter
          clearable
          @update:search="teamSearch = $event ?? ''"
        />
      </v-col>
    </v-row>
  </v-card>

  <ErrorAlert
    v-if="players.error.value"
    :error="players.error.value"
    retryable
    @retry="players.run()"
  />

  <v-card>
    <v-data-table-server
      :headers="headers"
      :items="players.data.value?.data ?? []"
      :items-length="players.data.value?.meta.total ?? 0"
      :loading="players.loading.value"
      :page="options.page"
      :items-per-page="options.itemsPerPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="options.sortBy"
      mobile-breakpoint="sm"
      loading-text="Cargando jugadores…"
      no-data-text="No hay jugadores guardados con esos filtros. Importa una liga para ver jugadores aquí."
      @update:options="onOptions"
    >
      <template #[`item.shirt_number`]="{ item }">
        <v-avatar size="30" color="primary" variant="tonal">
          <span class="text-caption font-weight-bold">{{ item.shirt_number ?? '–' }}</span>
        </v-avatar>
      </template>
      <template #[`item.position`]="{ item }">{{ positionLabel(item.position) }}</template>
      <template #[`item.team`]="{ item }">
        <RouterLink
          v-if="item.team"
          :to="`/teams/${item.team.id}`"
          class="d-inline-flex align-center ga-2 text-decoration-none"
        >
          <CrestAvatar :src="item.team.crest" :name="item.team.name" size="24" />
          {{ item.team.short_name ?? item.team.name }}
        </RouterLink>
      </template>
      <template #[`item.date_of_birth`]="{ item }">{{ item.age ?? '—' }}</template>
    </v-data-table-server>
  </v-card>
</template>
