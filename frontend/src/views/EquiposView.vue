<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useAbility } from '@casl/vue'
import type { HabilidadesApp } from '@/habilidades'
import { apiFutbol } from '@/api/futbol'
import { useAsincrono } from '@/composables/useAsincrono'
import { useRefConRetraso } from '@/composables/useRefConRetraso'
import EstadoAsincrono from '@/components/EstadoAsincrono.vue'
import EncabezadoPagina from '@/components/EncabezadoPagina.vue'
import AvatarEscudo from '@/components/AvatarEscudo.vue'

const { can } = useAbility<HabilidadesApp>()

const buscar = useRefConRetraso('')
const ligaId = ref<number | null>(null)
const tipo = ref<'club' | 'seleccion' | null>(null)
const tipos = [
  { title: 'Clubes', value: 'club' },
  { title: 'Selecciones', value: 'seleccion' },
]
const pagina = ref(1)

const { datos, cargando, error, ejecutar } = useAsincrono(() =>
  apiFutbol.equipos({
    buscar: buscar.value,
    liga_id: ligaId.value ?? undefined,
    tipo: tipo.value ?? undefined,
    page: pagina.value,
    por_pagina: 24,
  }),
)

watch([buscar, ligaId, tipo], () => {
  pagina.value = 1
  ejecutar()
})
watch(pagina, () => ejecutar())

// Solo las ligas importadas tienen sentido como filtro.
const ligas = useAsincrono(() => apiFutbol.ligas({ importadas: true }))

onMounted(() => {
  ejecutar()
  if (can('ver', 'Liga')) ligas.ejecutar()
})
</script>

<template>
  <EncabezadoPagina titulo="Equipos" subtitulo="Todos los equipos guardados al importar ligas." />

  <v-card class="mb-6 pa-4" variant="flat" border>
    <v-row dense>
      <v-col cols="12" md="5">
        <v-text-field
          :model-value="buscar"
          label="Buscar por nombre o sigla"
          prepend-inner-icon="mdi-magnify"
          clearable
          @update:model-value="buscar = $event ?? ''"
        />
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-autocomplete
          v-model="ligaId"
          :items="ligas.datos.value?.data ?? []"
          :loading="ligas.cargando.value"
          :disabled="!can('ver', 'Liga')"
          item-title="nombre"
          item-value="id"
          label="Liga"
          clearable
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-select v-model="tipo" :items="tipos" label="Tipo" clearable />
      </v-col>
    </v-row>
  </v-card>

  <EstadoAsincrono
    :cargando="cargando"
    :error="error"
    :vacio="datos?.data.length === 0"
    mantener-contenido
    icono-vacio="mdi-shield-off-outline"
    titulo-vacio="Todavía no hay equipos"
    :texto-vacio="
      buscar || ligaId || tipo
        ? 'Ningún equipo coincide con los filtros.'
        : 'Importa una liga para ver sus equipos aquí.'
    "
    @reintentar="ejecutar"
  >
    <template #acciones-vacio>
      <v-btn v-if="!buscar && !ligaId && !tipo && can('ver', 'Liga')" to="/ligas" color="primary"
        >Ver ligas</v-btn
      >
    </template>

    <v-progress-linear v-if="cargando" indeterminate color="primary" class="mb-2" />
    <v-skeleton-loader v-if="cargando && !datos" type="card, card" class="bg-transparent" />

    <v-row>
      <v-col v-for="equipo in datos?.data" :key="equipo.id" cols="12" sm="6" md="4" lg="3">
        <v-card :to="`/equipos/${equipo.id}`" height="100%" hover>
          <v-card-item>
            <template #prepend>
              <AvatarEscudo :src="equipo.escudo" :nombre="equipo.nombre" tamano="44" />
            </template>
            <v-card-title class="text-body-1 font-weight-medium text-wrap">{{
              equipo.nombre
            }}</v-card-title>
            <v-card-subtitle
              >{{ equipo.tipo === 'seleccion' ? 'Selección' : 'Club' }} · {{ equipo.area }} ·
              {{ equipo.sigla }}</v-card-subtitle
            >
          </v-card-item>
          <v-card-text class="d-flex ga-2 flex-wrap">
            <v-chip size="x-small" prepend-icon="mdi-account-group"
              >{{ equipo.cantidad_jugadores }} jugadores</v-chip
            >
            <v-chip v-for="liga in equipo.ligas" :key="liga.id" size="x-small" variant="outlined">
              {{ liga.codigo ?? liga.nombre }}
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="datos && datos.meta.last_page > 1" class="d-flex justify-center mt-6">
      <v-pagination
        v-model="pagina"
        :length="datos.meta.last_page"
        :total-visible="5"
        density="comfortable"
      />
    </div>
  </EstadoAsincrono>
</template>
