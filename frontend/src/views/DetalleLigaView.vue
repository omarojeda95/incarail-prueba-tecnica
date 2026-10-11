<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAbility } from '@casl/vue'
import type { HabilidadesApp } from '@/habilidades'
import { apiFutbol } from '@/api/futbol'
import { aErrorApi } from '@/api/errores'
import { useAsincrono } from '@/composables/useAsincrono'
import { useAvisosStore } from '@/stores/avisos'
import EstadoAsincrono from '@/components/EstadoAsincrono.vue'
import EncabezadoPagina from '@/components/EncabezadoPagina.vue'
import AvisoDesactualizado from '@/components/AvisoDesactualizado.vue'
import AvatarEscudo from '@/components/AvatarEscudo.vue'
import { formatearFecha, formatearFechaHora } from '@/utilidades/formato'

const props = defineProps<{ id: string }>()
const { can } = useAbility<HabilidadesApp>()
const avisos = useAvisosStore()

const { datos, cargando, error, ejecutar } = useAsincrono(() => apiFutbol.liga(props.id))
watch(
  () => props.id,
  () => ejecutar(),
  { immediate: true },
)

const liga = computed(() => datos.value?.data)
const filtro = ref('')
const equipos = computed(() => {
  const texto = filtro.value.toLowerCase()
  return (liga.value?.equipos ?? []).filter(
    (equipo) =>
      !texto ||
      equipo.nombre.toLowerCase().includes(texto) ||
      equipo.sigla?.toLowerCase().includes(texto),
  )
})

/** Torneos como la Champions llegan sin planteles: football-data solo los manda en las ligas nacionales. */
const equiposSinPlantel = computed(
  () => (liga.value?.equipos ?? []).filter((equipo) => !equipo.cantidad_jugadores).length,
)

const encolando = ref(false)
async function actualizarEnSegundoPlano() {
  encolando.value = true
  try {
    avisos.info((await apiFutbol.encolarImportacion(props.id)).mensaje)
  } catch (e) {
    avisos.error(aErrorApi(e).message)
  } finally {
    encolando.value = false
  }
}
</script>

<template>
  <EncabezadoPagina :titulo="liga?.nombre ?? 'Liga'" :volver="{ name: 'ligas' }">
    <template v-if="liga" #antes>
      <AvatarEscudo :src="liga.emblema" :nombre="liga.nombre" tamano="56" />
    </template>
    <template v-if="liga" #subtitulo>
      {{ liga.area.nombre }} · Temporada {{ formatearFecha(liga.temporada.inicio) }} –
      {{ formatearFecha(liga.temporada.fin) }}
      <template v-if="liga.temporada.jornada_actual">
        · Fecha {{ liga.temporada.jornada_actual }}</template
      >
    </template>
    <template #acciones>
      <v-btn
        v-if="can('importar', 'Liga') && liga"
        prepend-icon="mdi-cloud-sync-outline"
        variant="tonal"
        color="primary"
        :loading="encolando"
        @click="actualizarEnSegundoPlano"
      >
        Actualizar en segundo plano
      </v-btn>
    </template>
  </EncabezadoPagina>

  <AvisoDesactualizado :meta="datos?.meta" />

  <EstadoAsincrono
    :cargando="cargando"
    :error="error"
    :vacio="!!liga && liga.equipos?.length === 0"
    titulo-vacio="Esta liga no tiene equipos registrados"
    texto-vacio="football-data.org no devolvió equipos para la temporada actual."
    esqueleto="list-item-avatar-two-line@6"
    @reintentar="ejecutar"
  >
    <template v-if="liga">
      <v-row class="mb-2" dense>
        <v-col cols="6" md="3">
          <v-card variant="tonal" color="primary" class="pa-4">
            <div class="text-caption">Equipos</div>
            <div class="text-h5 font-weight-bold">{{ liga.equipos?.length ?? 0 }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" md="3">
          <v-card variant="tonal" color="secondary" class="pa-4">
            <div class="text-caption">Jugadores</div>
            <div class="text-h5 font-weight-bold">
              {{ liga.ultima_importacion?.cantidad_jugadores ?? '—' }}
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card variant="tonal" class="pa-4">
            <div class="text-caption">Datos guardados</div>
            <div class="text-body-1">
              {{ formatearFechaHora(liga.equipos_sincronizados_en) }}
              <v-chip
                size="x-small"
                class="ml-1"
                :color="datos?.meta.fuente === 'base_de_datos' ? 'default' : 'success'"
              >
                {{
                  datos?.meta.fuente === 'base_de_datos' ? 'desde nuestra base' : 'recién importado'
                }}
              </v-chip>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-alert
        v-if="equiposSinPlantel > 0"
        type="info"
        variant="tonal"
        border="start"
        class="mb-4"
        title="Algunos equipos no tienen plantel"
      >
        football-data no envió los jugadores de {{ equiposSinPlantel }} de
        {{ liga.equipos?.length }} equipos. Pasa con torneos internacionales como la Champions
        League: el proveedor solo entrega los planteles en las ligas nacionales. Al importar la liga
        nacional de cada equipo, sus jugadores aparecen también aquí.
      </v-alert>

      <div class="d-flex align-center ga-4 my-4 flex-wrap">
        <h2 class="text-h6 flex-grow-1">Equipos</h2>
        <v-text-field
          v-model="filtro"
          label="Filtrar equipos"
          prepend-inner-icon="mdi-magnify"
          density="compact"
          clearable
          style="max-width: 320px; min-width: 200px"
        />
      </div>

      <v-row>
        <v-col v-for="equipo in equipos" :key="equipo.id" cols="12" sm="6" md="4" lg="3">
          <v-card
            :to="can('ver', 'Equipo') ? `/equipos/${equipo.id}` : undefined"
            height="100%"
            hover
          >
            <v-card-item>
              <template #prepend>
                <AvatarEscudo :src="equipo.escudo" :nombre="equipo.nombre" tamano="44" />
              </template>
              <v-card-title class="text-body-1 font-weight-medium text-wrap">{{
                equipo.nombre
              }}</v-card-title>
              <v-card-subtitle>{{ equipo.estadio ?? 'Estadio sin datos' }}</v-card-subtitle>
            </v-card-item>
            <v-card-text class="d-flex ga-2 flex-wrap">
              <v-chip size="x-small" prepend-icon="mdi-account-group"
                >{{ equipo.cantidad_jugadores }} jugadores</v-chip
              >
              <v-chip v-if="equipo.fundacion" size="x-small" prepend-icon="mdi-calendar">{{
                equipo.fundacion
              }}</v-chip>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      <v-empty-state
        v-if="equipos.length === 0"
        icon="mdi-magnify-close"
        title="Ningún equipo coincide con el filtro"
      />
    </template>
  </EstadoAsincrono>
</template>
