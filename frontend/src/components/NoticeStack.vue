<script setup lang="ts">
import { computed } from 'vue'
import { useNotifyStore } from '@/stores/notify'

const notify = useNotifyStore()
const current = computed(() => notify.notices[0])
</script>

<template>
  <v-snackbar
    v-if="current"
    :key="current.id"
    :model-value="true"
    :color="current.color"
    :timeout="current.timeout"
    location="bottom right"
    multi-line
    @update:model-value="notify.dismiss(current.id)"
  >
    {{ current.text }}
    <template #actions>
      <v-btn
        variant="text"
        icon="mdi-close"
        aria-label="Cerrar"
        @click="notify.dismiss(current.id)"
      />
    </template>
  </v-snackbar>
</template>
