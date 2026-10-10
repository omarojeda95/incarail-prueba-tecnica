<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { apiFutbol } from '@/api/futbol'
import { useAsincrono } from '@/composables/useAsincrono'
import { useRefConRetraso } from '@/composables/useRefConRetraso'
import EstadoAsincrono from '@/components/EstadoAsincrono.vue'
import EncabezadoPagina from '@/components/EncabezadoPagina.vue'
import AvisoDesactualizado from '@/components/AvisoDesactualizado.vue'
import AvatarEscudo from '@/components/AvatarEscudo.vue'
import { formatearFecha } from '@/utilidades/formato'

const buscar = useRefConRetraso('')
const soloDisponibles = ref(true)
const soloImportadas = ref(false)
const area = ref<string | null>(null)

const { datos, cargando, error, ejecutar } = useAsincrono(() =>
  apiFutbol.ligas({
    buscar: buscar.value,
    disponibles: soloDisponibles.value || undefined,
    importadas: soloImportadas.value || undefined,
  }),
)

watch([buscar, soloDisponibles, soloImportadas], () => ejecutar(), { immediate: true })

const areas = computed(() =>
  [
    ...new Set(
      (datos.value?.data ?? [])
        .map((l) => l.area.nombre)
        .filter((nombre): nombre is string => !!nombre),
    ),
  ].sort(),
)
const ligas = computed(() =>
  (datos.value?.data ?? []).filter((l) => !area.value || l.area.nombre === area.value),
)
</script>

<template>
  <EncabezadoPagina
    titulo="Ligas"
    subtitulo="Competiciones de football-data.org. Al abrir una liga se guardan sus equipos y jugadores."
  />

  <v-card class="mb-6 pa-4" variant="flat" border>
    <v-row dense align="center">
      <v-col cols="12" md="5">
        <v-text-field
          :model-value="buscar"
          label="Buscar liga, código o país"
          prepend-inner-icon="mdi-magnify"
          clearable
          @update:model-value="buscar = $event ?? ''"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-autocomplete v-model="area" :items="areas" label="País / región" clearable />
      </v-col>
      <v-col cols="6" sm="3" md="2">
        <v-switch
          v-model="soloDisponibles"
          label="Disponibles"
          color="primary"
          hide-details
          inset
        />
      </v-col>
      <v-col cols="6" sm="3" md="2">
        <v-switch v-model="soloImportadas" label="Importadas" color="primary" hide-details inset />
      </v-col>
    </v-row>
  </v-card>

  <AvisoDesactualizado :meta="datos?.meta" />

  <EstadoAsincrono
    :cargando="cargando"
    :error="error"
    :vacio="ligas.length === 0"
    icono-vacio="mdi-trophy-broken"
    titulo-vacio="No hay ligas con esos filtros"
    texto-vacio="Prueba con otra búsqueda o desactiva los filtros."
    esqueleto="card, card"
    @reintentar="ejecutar"
  >
    <v-row>
      <v-col v-for="liga in ligas" :key="liga.id" cols="12" sm="6" lg="4" xl="3">
        <v-card
          :to="liga.disponible || liga.importada ? `/ligas/${liga.codigo ?? liga.id}` : undefined"
          :disabled="!liga.disponible && !liga.importada"
          height="100%"
          variant="elevated"
          hover
        >
          <v-card-item>
            <template #prepend>
              <AvatarEscudo :src="liga.emblema" :nombre="liga.nombre" tamano="48" />
            </template>
            <v-card-title class="text-wrap">{{ liga.nombre }}</v-card-title>
            <v-card-subtitle>{{ liga.area.nombre }} · {{ liga.codigo ?? liga.id }}</v-card-subtitle>
          </v-card-item>
          <v-card-text>
            <div class="text-body-2 text-medium-emphasis mb-3">
              <v-icon icon="mdi-calendar-range" size="small" />
              {{ formatearFecha(liga.temporada.inicio) }} – {{ formatearFecha(liga.temporada.fin) }}
            </div>
            <v-chip v-if="liga.importada" color="success" size="small" prepend-icon="mdi-check">
              Importada · {{ liga.cantidad_equipos }} equipos
            </v-chip>
            <v-chip
              v-else-if="liga.disponible"
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
  </EstadoAsincrono>
</template>
