<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { footballApi } from '@/api/football'
import { useAsync } from '@/composables/useAsync'
import { useDebouncedRef } from '@/composables/useDebounced'
import AsyncState from '@/components/AsyncState.vue'
import PageHeader from '@/components/PageHeader.vue'
import StaleBanner from '@/components/StaleBanner.vue'
import CrestAvatar from '@/components/CrestAvatar.vue'
import { formatDate } from '@/utils/format'

const search = useDebouncedRef('')
const onlyAvailable = ref(true)
const onlyImported = ref(false)
const area = ref<string | null>(null)

const { data, loading, error, run } = useAsync(() =>
  footballApi.competitions({
    search: search.value,
    available: onlyAvailable.value || undefined,
    imported: onlyImported.value || undefined,
  }),
)

watch([search, onlyAvailable, onlyImported], () => run(), { immediate: true })

const areas = computed(() =>
  [
    ...new Set(
      (data.value?.data ?? []).map((c) => c.area.name).filter((name): name is string => !!name),
    ),
  ].sort(),
)
const competitions = computed(() =>
  (data.value?.data ?? []).filter((c) => !area.value || c.area.name === area.value),
)
</script>

<template>
  <PageHeader
    title="Ligas"
    subtitle="Competiciones de football-data.org. Al abrir una liga se guardan sus equipos y jugadores."
  />

  <v-card class="mb-6 pa-4" variant="flat" border>
    <v-row dense align="center">
      <v-col cols="12" md="5">
        <v-text-field
          :model-value="search"
          label="Buscar liga, código o país"
          prepend-inner-icon="mdi-magnify"
          clearable
          @update:model-value="search = $event ?? ''"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-autocomplete v-model="area" :items="areas" label="País / región" clearable />
      </v-col>
      <v-col cols="6" sm="3" md="2">
        <v-switch v-model="onlyAvailable" label="Disponibles" color="primary" hide-details inset />
      </v-col>
      <v-col cols="6" sm="3" md="2">
        <v-switch v-model="onlyImported" label="Importadas" color="primary" hide-details inset />
      </v-col>
    </v-row>
  </v-card>

  <StaleBanner :meta="data?.meta" />

  <AsyncState
    :loading="loading"
    :error="error"
    :empty="competitions.length === 0"
    empty-icon="mdi-trophy-broken"
    empty-title="No hay ligas con esos filtros"
    empty-text="Prueba con otra búsqueda o desactiva los filtros."
    skeleton="card, card"
    @retry="run"
  >
    <v-row>
      <v-col
        v-for="competition in competitions"
        :key="competition.id"
        cols="12"
        sm="6"
        lg="4"
        xl="3"
      >
        <v-card
          :to="
            competition.available || competition.imported
              ? `/competitions/${competition.code ?? competition.id}`
              : undefined
          "
          :disabled="!competition.available && !competition.imported"
          height="100%"
          variant="elevated"
          hover
        >
          <v-card-item>
            <template #prepend>
              <CrestAvatar :src="competition.emblem" :name="competition.name" size="48" />
            </template>
            <v-card-title class="text-wrap">{{ competition.name }}</v-card-title>
            <v-card-subtitle>
              {{ competition.area.name }} · {{ competition.code ?? competition.id }}
            </v-card-subtitle>
          </v-card-item>
          <v-card-text>
            <div class="text-body-2 text-medium-emphasis mb-3">
              <v-icon icon="mdi-calendar-range" size="small" />
              {{ formatDate(competition.season.start) }} – {{ formatDate(competition.season.end) }}
            </div>
            <v-chip
              v-if="competition.imported"
              color="success"
              size="small"
              prepend-icon="mdi-check"
            >
              Importada · {{ competition.teams_count }} equipos
            </v-chip>
            <v-chip
              v-else-if="competition.available"
              color="primary"
              size="small"
              variant="outlined"
              prepend-icon="mdi-download"
            >
              Lista para importar
            </v-chip>
            <v-chip v-else size="small" variant="outlined" prepend-icon="mdi-lock-outline">
              Fuera del plan gratuito
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </AsyncState>
</template>
