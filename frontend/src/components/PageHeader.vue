<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

defineProps<{
  title: string
  subtitle?: string
  back?: RouteLocationRaw
}>()
</script>

<template>
  <div class="d-flex flex-wrap align-center ga-3 mb-6">
    <v-btn v-if="back" :to="back" icon="mdi-arrow-left" variant="text" aria-label="Volver" />
    <slot name="prepend" />
    <div class="flex-grow-1" style="min-width: 0">
      <h1 class="text-h5 text-sm-h4 font-weight-bold text-truncate">{{ title }}</h1>
      <div v-if="subtitle || $slots.subtitle" class="text-medium-emphasis text-body-2">
        <slot name="subtitle">{{ subtitle }}</slot>
      </div>
    </div>
    <div class="d-flex flex-wrap ga-2">
      <slot name="actions" />
    </div>
  </div>
</template>
