<script setup lang="ts">
import { computed } from 'vue'
import { useAvisosStore } from '@/stores/avisos'

const avisos = useAvisosStore()
const actual = computed(() => avisos.avisos[0])
</script>

<template>
  <v-snackbar
    v-if="actual"
    :key="actual.id"
    :model-value="true"
    :color="actual.color"
    :timeout="actual.duracion"
    location="bottom right"
    multi-line
    @update:model-value="avisos.descartar(actual.id)"
  >
    {{ actual.texto }}
    <template #actions>
      <v-btn
        variant="text"
        icon="mdi-close"
        aria-label="Cerrar"
        @click="avisos.descartar(actual.id)"
      />
    </template>
  </v-snackbar>
</template>
