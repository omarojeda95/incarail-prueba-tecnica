<script setup lang="ts">
import { computed, watch } from 'vue'
import type { ApiError } from '@/api/errors'
import { useCountdown } from '@/composables/useCountdown'

const props = defineProps<{ error: ApiError; retryable?: boolean }>()
const emit = defineEmits<{ retry: [] }>()

const countdown = useCountdown()

// After a rate limit, retry by itself once a slot is free again.
watch(
  () => props.error,
  (error) => {
    if (error.isRateLimited && error.retryAfter) {
      countdown.start(error.retryAfter + 1, () => props.retryable && emit('retry'))
    } else {
      countdown.stop()
    }
  },
  { immediate: true },
)

const look = computed(() => {
  const { error } = props
  if (error.isRateLimited)
    return {
      type: 'warning',
      icon: 'mdi-timer-sand',
      title: 'Límite de consultas alcanzado',
    } as const
  if (error.isUpstreamDown)
    return {
      type: 'error',
      icon: 'mdi-cloud-off-outline',
      title: 'football-data.org no está disponible',
    } as const
  if (error.status === 403)
    return { type: 'warning', icon: 'mdi-lock-outline', title: 'Sin permiso' } as const
  if (error.status === 404)
    return { type: 'info', icon: 'mdi-help-circle-outline', title: 'No encontrado' } as const
  if (error.code === 'network_error')
    return { type: 'error', icon: 'mdi-lan-disconnect', title: 'Sin conexión con la API' } as const
  return { type: 'error', icon: 'mdi-alert-circle-outline', title: 'Algo salió mal' } as const
})
</script>

<template>
  <v-alert :type="look.type" :icon="look.icon" variant="tonal" border="start" class="mb-4">
    <template #title>{{ look.title }}</template>
    <div>{{ error.message }}</div>
    <div v-if="countdown.running.value" class="text-body-2 mt-1">
      <template v-if="retryable"
        >Reintentaremos automáticamente en {{ countdown.remaining.value }} s.</template
      >
      <template v-else>Podrás reintentar en {{ countdown.remaining.value }} s.</template>
    </div>
    <template v-if="retryable" #append>
      <v-btn
        variant="outlined"
        size="small"
        :disabled="countdown.running.value"
        @click="emit('retry')"
      >
        Reintentar
      </v-btn>
    </template>
  </v-alert>
</template>
