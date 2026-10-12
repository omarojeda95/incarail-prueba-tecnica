<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { apiFutbol } from '@/api/futbol'
import { useAsincrono } from '@/composables/useAsincrono'
import EstadoAsincrono from '@/components/EstadoAsincrono.vue'
import EncabezadoPagina from '@/components/EncabezadoPagina.vue'
import AvatarEscudo from '@/components/AvatarEscudo.vue'
import { etiquetaPosicion, ordenPosicion } from '@/utilidades/formato'
import type { Jugador } from '@/tipos/api'

const props = defineProps<{ id: string }>()

const { datos: equipo, cargando, error, ejecutar } = useAsincrono(() => apiFutbol.equipo(props.id))
watch(
  () => props.id,
  () => ejecutar(),
  { immediate: true },
)

const filtro = ref('')

const grupos = computed(() => {
  const texto = filtro.value.toLowerCase()
  const jugadores = (equipo.value?.jugadores ?? []).filter(
    (j) => !texto || j.nombre.toLowerCase().includes(texto),
  )
  const porLinea = new Map<number, Jugador[]>()
  for (const jugador of jugadores) {
    const orden = ordenPosicion(jugador.posicion)
    porLinea.set(orden, [...(porLinea.get(orden) ?? []), jugador])
  }
  const titulos: Record<number, string> = {
    1: 'Arqueros',
    2: 'Defensas',
    3: 'Mediocampistas',
    4: 'Delanteros',
    9: 'Otros',
  }
  return [...porLinea.entries()]
    .sort(([a], [b]) => a - b)
    .map(([orden, lista]) => ({ titulo: titulos[orden], jugadores: lista }))
})

const ficha = computed(() => {
  const e = equipo.value
  if (!e) return []
  return [
    { icono: 'mdi-stadium', etiqueta: 'Estadio', valor: e.estadio },
    { icono: 'mdi-calendar', etiqueta: 'Fundación', valor: e.fundacion },
    { icono: 'mdi-palette', etiqueta: 'Colores', valor: e.colores },
    { icono: 'mdi-whistle', etiqueta: 'Entrenador', valor: e.entrenador },
    { icono: 'mdi-map-marker', etiqueta: 'Dirección', valor: e.direccion },
  ].filter((dato) => dato.valor)
})
</script>

<template>
  <EncabezadoPagina :titulo="equipo?.nombre ?? 'Equipo'" :volver="{ name: 'equipos' }">
    <template v-if="equipo" #antes>
      <AvatarEscudo :src="equipo.escudo" :nombre="equipo.nombre" tamano="56" />
    </template>
    <template v-if="equipo" #subtitulo>
      {{ equipo.tipo === 'seleccion' ? 'Selección' : 'Club' }} · {{ equipo.area }} ·
      {{ equipo.sigla }}
      <a
        v-if="equipo.sitio_web"
        :href="equipo.sitio_web"
        target="_blank"
        rel="noopener"
        class="ml-2"
        >Sitio oficial</a
      >
    </template>
  </EncabezadoPagina>

  <EstadoAsincrono
    :cargando="cargando"
    :error="error"
    esqueleto="article, table"
    @reintentar="ejecutar"
  >
    <template v-if="equipo">
      <v-row>
        <v-col cols="12" md="4">
          <v-card title="Ficha" class="mb-4">
            <v-list density="compact">
              <v-list-item
                v-for="dato in ficha"
                :key="dato.etiqueta"
                :prepend-icon="dato.icono"
                :subtitle="dato.etiqueta"
              >
                <v-list-item-title class="text-wrap">{{ dato.valor }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card>
          <v-card title="Ligas">
            <v-card-text class="d-flex flex-wrap ga-2">
              <v-chip
                v-for="liga in equipo.ligas"
                :key="liga.id"
                :to="`/ligas/${liga.codigo ?? liga.id}`"
                prepend-icon="mdi-trophy-outline"
              >
                {{ liga.nombre }}
              </v-chip>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="8">
          <v-card>
            <v-card-item>
              <v-card-title>Plantel ({{ equipo.jugadores?.length ?? 0 }})</v-card-title>
              <template #append>
                <v-text-field
                  v-model="filtro"
                  label="Buscar jugador"
                  prepend-inner-icon="mdi-magnify"
                  density="compact"
                  clearable
                  style="min-width: 200px"
                />
              </template>
            </v-card-item>

            <v-empty-state
              v-if="grupos.length === 0 && filtro"
              icon="mdi-account-off-outline"
              title="Ningún jugador coincide"
            />
            <v-empty-state
              v-else-if="grupos.length === 0"
              icon="mdi-account-off-outline"
              title="Este equipo no tiene jugadores guardados"
              text="Si es un club de la Champions, importa su liga nacional: en la Champions football-data no envía los planteles."
            />

            <template v-for="(grupo, indice) in grupos" :key="grupo.titulo">
              <v-divider v-if="indice > 0" class="mt-4" />
              <div class="text-subtitle-2 font-weight-bold text-primary px-4 pt-4 pb-1">
                {{ grupo.titulo }} ({{ grupo.jugadores.length }})
              </div>
              <v-table density="comfortable" class="tabla-plantel">
                <tbody>
                  <tr v-for="jugador in grupo.jugadores" :key="jugador.id">
                    <td class="col-numero">
                      <v-avatar size="32" color="primary" variant="tonal">
                        <span class="text-caption font-weight-bold">{{
                          jugador.numero_camiseta ?? '–'
                        }}</span>
                      </v-avatar>
                    </td>
                    <td>
                      <div class="font-weight-medium">{{ jugador.nombre }}</div>
                      <div class="text-caption text-medium-emphasis">
                        {{ etiquetaPosicion(jugador.posicion) }}
                      </div>
                    </td>
                    <td class="d-none d-sm-table-cell col-nacionalidad">
                      {{ jugador.nacionalidad }}
                    </td>
                    <td class="text-right text-no-wrap col-edad">
                      {{ jugador.edad ? `${jugador.edad} años` : '' }}
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </template>
            <div class="pb-2" />
          </v-card>
        </v-col>
      </v-row>
    </template>
  </EstadoAsincrono>
</template>

<style scoped>
/* Cada línea del plantel es una tabla: anchos fijos para que las columnas queden alineadas. */
.tabla-plantel :deep(table) {
  table-layout: fixed;
}
.col-numero {
  width: 64px;
}
.col-nacionalidad {
  width: 32%;
}
.col-edad {
  width: 96px;
}
</style>
