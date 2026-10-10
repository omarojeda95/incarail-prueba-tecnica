<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

defineProps<{
  titulo: string
  subtitulo?: string
  volver?: RouteLocationRaw
}>()
</script>

<template>
  <div class="d-flex flex-wrap align-center ga-3 mb-6">
    <v-btn v-if="volver" :to="volver" icon="mdi-arrow-left" variant="text" aria-label="Volver" />
    <slot name="antes" />
    <div class="flex-grow-1" style="min-width: 0">
      <h1 class="text-h5 text-sm-h4 font-weight-bold text-truncate">{{ titulo }}</h1>
      <div v-if="subtitulo || $slots.subtitulo" class="text-medium-emphasis text-body-2">
        <slot name="subtitulo">{{ subtitulo }}</slot>
      </div>
    </div>
    <div class="d-flex flex-wrap ga-2">
      <slot name="acciones" />
    </div>
  </div>
</template>
