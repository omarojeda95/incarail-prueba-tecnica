<script setup lang="ts">
import type { SourceMeta } from '@/types/api'
import { formatDateTime } from '@/utils/format'

defineProps<{ meta: SourceMeta | null | undefined }>()
</script>

<template>
  <v-alert
    v-if="meta?.stale"
    type="info"
    variant="tonal"
    density="compact"
    icon="mdi-database-clock-outline"
    class="mb-4"
  >
    Mostrando la copia guardada
    <template v-if="meta.synced_at">del {{ formatDateTime(meta.synced_at) }}</template>
    porque football-data.org no está disponible o se alcanzó el límite de consultas.
    <template v-if="meta.retry_after"> La actualización quedó en cola.</template>
  </v-alert>
</template>
