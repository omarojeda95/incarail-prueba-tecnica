<script setup lang="ts">
import type { MetaFuente } from '@/tipos/api'
import { formatearFechaHora } from '@/utilidades/formato'

defineProps<{ meta: MetaFuente | null | undefined }>()
</script>

<template>
  <v-alert
    v-if="meta?.desactualizado"
    type="info"
    variant="tonal"
    density="compact"
    icon="mdi-database-clock-outline"
    class="mb-4"
  >
    Mostrando la copia guardada
    <template v-if="meta.sincronizado_en"
      >del {{ formatearFechaHora(meta.sincronizado_en) }}</template
    >
    porque football-data.org no está disponible o se alcanzó el límite de consultas.
    <template v-if="meta.reintentar_en"> La actualización quedó en cola.</template>
  </v-alert>
</template>
