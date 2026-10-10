<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAbility } from '@casl/vue'
import type { AppAbility } from '@/ability'
import { footballApi } from '@/api/football'
import { toApiError } from '@/api/errors'
import { useAsync } from '@/composables/useAsync'
import { useNotifyStore } from '@/stores/notify'
import AsyncState from '@/components/AsyncState.vue'
import PageHeader from '@/components/PageHeader.vue'
import StaleBanner from '@/components/StaleBanner.vue'
import CrestAvatar from '@/components/CrestAvatar.vue'
import { formatDate, formatDateTime } from '@/utils/format'

const props = defineProps<{ id: string }>()
const { can } = useAbility<AppAbility>()
const notify = useNotifyStore()

const { data, loading, error, run } = useAsync(() => footballApi.competition(props.id))
watch(
  () => props.id,
  () => run(),
  { immediate: true },
)

const competition = computed(() => data.value?.data)
const filter = ref('')
const teams = computed(() => {
  const term = filter.value.toLowerCase()
  return (competition.value?.teams ?? []).filter(
    (team) =>
      !term || team.name.toLowerCase().includes(term) || team.tla?.toLowerCase().includes(term),
  )
})

const queueing = ref(false)
async function queueRefresh() {
  queueing.value = true
  try {
    notify.info((await footballApi.queueImport(props.id)).message)
  } catch (e) {
    notify.error(toApiError(e).message)
  } finally {
    queueing.value = false
  }
}
</script>

<template>
  <PageHeader :title="competition?.name ?? 'Liga'" :back="{ name: 'competitions' }">
    <template v-if="competition" #prepend>
      <CrestAvatar :src="competition.emblem" :name="competition.name" size="56" />
    </template>
    <template v-if="competition" #subtitle>
      {{ competition.area.name }} · Temporada {{ formatDate(competition.season.start) }} –
      {{ formatDate(competition.season.end) }}
      <template v-if="competition.season.current_matchday">
        · Fecha {{ competition.season.current_matchday }}</template
      >
    </template>
    <template #actions>
      <v-btn
        v-if="can('import', 'Competition') && competition"
        prepend-icon="mdi-cloud-sync-outline"
        variant="tonal"
        color="primary"
        :loading="queueing"
        @click="queueRefresh"
      >
        Actualizar en segundo plano
      </v-btn>
    </template>
  </PageHeader>

  <StaleBanner :meta="data?.meta" />

  <AsyncState
    :loading="loading"
    :error="error"
    :empty="!!competition && competition.teams?.length === 0"
    empty-title="Esta liga no tiene equipos registrados"
    empty-text="football-data.org no devolvió equipos para la temporada actual."
    skeleton="list-item-avatar-two-line@6"
    @retry="run"
  >
    <template v-if="competition">
      <v-row class="mb-2" dense>
        <v-col cols="6" md="3">
          <v-card variant="tonal" color="primary" class="pa-4">
            <div class="text-caption">Equipos</div>
            <div class="text-h5 font-weight-bold">{{ competition.teams?.length ?? 0 }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" md="3">
          <v-card variant="tonal" color="secondary" class="pa-4">
            <div class="text-caption">Jugadores</div>
            <div class="text-h5 font-weight-bold">
              {{ competition.last_import?.players_count ?? '—' }}
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card variant="tonal" class="pa-4">
            <div class="text-caption">Datos guardados</div>
            <div class="text-body-1">
              {{ formatDateTime(competition.teams_synced_at) }}
              <v-chip
                size="x-small"
                class="ml-1"
                :color="data?.meta.source === 'database' ? 'default' : 'success'"
              >
                {{ data?.meta.source === 'database' ? 'desde nuestra base' : 'recién importado' }}
              </v-chip>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <div class="d-flex align-center ga-4 my-4 flex-wrap">
        <h2 class="text-h6 flex-grow-1">Equipos</h2>
        <v-text-field
          v-model="filter"
          label="Filtrar equipos"
          prepend-inner-icon="mdi-magnify"
          density="compact"
          clearable
          style="max-width: 320px; min-width: 200px"
        />
      </div>

      <v-row>
        <v-col v-for="team in teams" :key="team.id" cols="12" sm="6" md="4" lg="3">
          <v-card :to="can('read', 'Team') ? `/teams/${team.id}` : undefined" height="100%" hover>
            <v-card-item>
              <template #prepend>
                <CrestAvatar :src="team.crest" :name="team.name" size="44" />
              </template>
              <v-card-title class="text-body-1 font-weight-medium text-wrap">{{
                team.name
              }}</v-card-title>
              <v-card-subtitle>{{ team.venue ?? 'Estadio sin datos' }}</v-card-subtitle>
            </v-card-item>
            <v-card-text class="d-flex ga-2 flex-wrap">
              <v-chip size="x-small" prepend-icon="mdi-account-group"
                >{{ team.players_count }} jugadores</v-chip
              >
              <v-chip v-if="team.founded" size="x-small" prepend-icon="mdi-calendar">{{
                team.founded
              }}</v-chip>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      <v-empty-state
        v-if="teams.length === 0"
        icon="mdi-magnify-close"
        title="Ningún equipo coincide con el filtro"
      />
    </template>
  </AsyncState>
</template>
