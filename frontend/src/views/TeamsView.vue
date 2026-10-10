<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useAbility } from '@casl/vue'
import type { AppAbility } from '@/ability'
import { footballApi } from '@/api/football'
import { useAsync } from '@/composables/useAsync'
import { useDebouncedRef } from '@/composables/useDebounced'
import AsyncState from '@/components/AsyncState.vue'
import PageHeader from '@/components/PageHeader.vue'
import CrestAvatar from '@/components/CrestAvatar.vue'

const { can } = useAbility<AppAbility>()

const search = useDebouncedRef('')
const competitionId = ref<number | null>(null)
const page = ref(1)

const { data, loading, error, run } = useAsync(() =>
  footballApi.teams({
    search: search.value,
    competition_id: competitionId.value ?? undefined,
    page: page.value,
    per_page: 24,
  }),
)

watch([search, competitionId], () => {
  page.value = 1
  run()
})
watch(page, () => run())

// Only imported competitions make sense as a filter.
const competitions = useAsync(() => footballApi.competitions({ imported: true }))

onMounted(() => {
  run()
  if (can('read', 'Competition')) competitions.run()
})
</script>

<template>
  <PageHeader title="Equipos" subtitle="Todos los equipos guardados al importar ligas." />

  <v-card class="mb-6 pa-4" variant="flat" border>
    <v-row dense>
      <v-col cols="12" md="7">
        <v-text-field
          :model-value="search"
          label="Buscar por nombre o sigla"
          prepend-inner-icon="mdi-magnify"
          clearable
          @update:model-value="search = $event ?? ''"
        />
      </v-col>
      <v-col cols="12" md="5">
        <v-autocomplete
          v-model="competitionId"
          :items="competitions.data.value?.data ?? []"
          :loading="competitions.loading.value"
          :disabled="!can('read', 'Competition')"
          item-title="name"
          item-value="id"
          label="Liga"
          clearable
        />
      </v-col>
    </v-row>
  </v-card>

  <AsyncState
    :loading="loading"
    :error="error"
    :empty="data?.data.length === 0"
    keep-content
    empty-icon="mdi-shield-off-outline"
    empty-title="Todavía no hay equipos"
    :empty-text="
      search || competitionId
        ? 'Ningún equipo coincide con los filtros.'
        : 'Importa una liga para ver sus equipos aquí.'
    "
    @retry="run"
  >
    <template #empty-actions>
      <v-btn
        v-if="!search && !competitionId && can('read', 'Competition')"
        to="/competitions"
        color="primary"
      >
        Ver ligas
      </v-btn>
    </template>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />
    <v-skeleton-loader v-if="loading && !data" type="card, card" class="bg-transparent" />

    <v-row>
      <v-col v-for="team in data?.data" :key="team.id" cols="12" sm="6" md="4" lg="3">
        <v-card :to="`/teams/${team.id}`" height="100%" hover>
          <v-card-item>
            <template #prepend>
              <CrestAvatar :src="team.crest" :name="team.name" size="44" />
            </template>
            <v-card-title class="text-body-1 font-weight-medium text-wrap">{{
              team.name
            }}</v-card-title>
            <v-card-subtitle>{{ team.area }} · {{ team.tla }}</v-card-subtitle>
          </v-card-item>
          <v-card-text class="d-flex ga-2 flex-wrap">
            <v-chip size="x-small" prepend-icon="mdi-account-group"
              >{{ team.players_count }} jugadores</v-chip
            >
            <v-chip v-for="c in team.competitions" :key="c.id" size="x-small" variant="outlined">
              {{ c.code ?? c.name }}
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="data && data.meta.last_page > 1" class="d-flex justify-center mt-6">
      <v-pagination
        v-model="page"
        :length="data.meta.last_page"
        :total-visible="5"
        density="comfortable"
      />
    </div>
  </AsyncState>
</template>
