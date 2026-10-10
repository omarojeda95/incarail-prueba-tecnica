<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { footballApi } from '@/api/football'
import { useAsync } from '@/composables/useAsync'
import AsyncState from '@/components/AsyncState.vue'
import PageHeader from '@/components/PageHeader.vue'
import CrestAvatar from '@/components/CrestAvatar.vue'
import { positionLabel, positionOrder } from '@/utils/format'
import type { Player } from '@/types/api'

const props = defineProps<{ id: string }>()

const { data: team, loading, error, run } = useAsync(() => footballApi.team(props.id))
watch(
  () => props.id,
  () => run(),
  { immediate: true },
)

const filter = ref('')

/** Squad grouped by line (goalkeepers, defenders, midfielders, forwards). */
const groups = computed(() => {
  const term = filter.value.toLowerCase()
  const players = (team.value?.players ?? []).filter(
    (p) => !term || p.name.toLowerCase().includes(term),
  )
  const byOrder = new Map<number, Player[]>()
  for (const player of players) {
    const order = positionOrder(player.position)
    byOrder.set(order, [...(byOrder.get(order) ?? []), player])
  }
  const titles: Record<number, string> = {
    1: 'Arqueros',
    2: 'Defensas',
    3: 'Mediocampistas',
    4: 'Delanteros',
    9: 'Otros',
  }
  return [...byOrder.entries()]
    .sort(([a], [b]) => a - b)
    .map(([order, list]) => ({ title: titles[order], players: list }))
})

const facts = computed(() => {
  const t = team.value
  if (!t) return []
  return [
    { icon: 'mdi-stadium', label: 'Estadio', value: t.venue },
    { icon: 'mdi-calendar', label: 'Fundación', value: t.founded },
    { icon: 'mdi-palette', label: 'Colores', value: t.club_colors },
    { icon: 'mdi-whistle', label: 'Entrenador', value: t.coach },
    { icon: 'mdi-map-marker', label: 'Dirección', value: t.address },
  ].filter((fact) => fact.value)
})
</script>

<template>
  <PageHeader :title="team?.name ?? 'Equipo'" :back="{ name: 'teams' }">
    <template v-if="team" #prepend>
      <CrestAvatar :src="team.crest" :name="team.name" size="56" />
    </template>
    <template v-if="team" #subtitle>
      {{ team.area }} · {{ team.tla }}
      <a v-if="team.website" :href="team.website" target="_blank" rel="noopener" class="ml-2"
        >Sitio oficial</a
      >
    </template>
  </PageHeader>

  <AsyncState :loading="loading" :error="error" skeleton="article, table" @retry="run">
    <template v-if="team">
      <v-row>
        <v-col cols="12" md="4">
          <v-card title="Ficha" class="mb-4">
            <v-list density="compact">
              <v-list-item
                v-for="fact in facts"
                :key="fact.label"
                :prepend-icon="fact.icon"
                :subtitle="fact.label"
              >
                <v-list-item-title class="text-wrap">{{ fact.value }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card>
          <v-card title="Competiciones">
            <v-card-text class="d-flex flex-wrap ga-2">
              <v-chip
                v-for="c in team.competitions"
                :key="c.id"
                :to="`/competitions/${c.code ?? c.id}`"
                prepend-icon="mdi-trophy-outline"
              >
                {{ c.name }}
              </v-chip>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="8">
          <v-card>
            <v-card-item>
              <v-card-title>Plantel ({{ team.players?.length ?? 0 }})</v-card-title>
              <template #append>
                <v-text-field
                  v-model="filter"
                  label="Buscar jugador"
                  prepend-inner-icon="mdi-magnify"
                  density="compact"
                  clearable
                  style="min-width: 200px"
                />
              </template>
            </v-card-item>

            <v-empty-state
              v-if="groups.length === 0"
              icon="mdi-account-off-outline"
              :title="
                filter ? 'Ningún jugador coincide' : 'Este equipo no tiene jugadores guardados'
              "
            />

            <template v-for="group in groups" :key="group.title">
              <v-list-subheader class="font-weight-bold">{{ group.title }}</v-list-subheader>
              <v-table density="comfortable">
                <tbody>
                  <tr v-for="player in group.players" :key="player.id">
                    <td style="width: 56px">
                      <v-avatar size="32" color="primary" variant="tonal">
                        <span class="text-caption font-weight-bold">{{
                          player.shirt_number ?? '–'
                        }}</span>
                      </v-avatar>
                    </td>
                    <td>
                      <div class="font-weight-medium">{{ player.name }}</div>
                      <div class="text-caption text-medium-emphasis">
                        {{ positionLabel(player.position) }}
                      </div>
                    </td>
                    <td class="d-none d-sm-table-cell">{{ player.nationality }}</td>
                    <td class="text-right text-no-wrap">
                      {{ player.age ? `${player.age} años` : '' }}
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </template>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </AsyncState>
</template>
