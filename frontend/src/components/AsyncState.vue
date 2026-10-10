<script setup lang="ts">
import type { ApiError } from '@/api/errors'
import ErrorAlert from './ErrorAlert.vue'

/**
 * Wraps any data block with the three states every screen needs:
 * loading (skeleton), error (with retry) and empty (with a hint).
 */
withDefaults(
  defineProps<{
    loading: boolean
    error: ApiError | null
    empty?: boolean
    emptyTitle?: string
    emptyText?: string
    emptyIcon?: string
    skeleton?: string
    /** Keep showing the content while reloading (e.g. paginating). */
    keepContent?: boolean
  }>(),
  {
    empty: false,
    emptyTitle: 'No hay nada que mostrar',
    emptyText: '',
    emptyIcon: 'mdi-inbox-outline',
    skeleton: 'card, card, card',
    keepContent: false,
  },
)

defineEmits<{ retry: [] }>()
</script>

<template>
  <ErrorAlert v-if="error" :error="error" retryable @retry="$emit('retry')" />

  <v-skeleton-loader v-if="loading && !keepContent" :type="skeleton" class="bg-transparent" />

  <v-empty-state
    v-else-if="empty && !error"
    :icon="emptyIcon"
    :title="emptyTitle"
    :text="emptyText"
    class="my-8"
  >
    <template v-if="$slots['empty-actions']" #actions>
      <slot name="empty-actions" />
    </template>
  </v-empty-state>

  <slot v-else-if="!error || keepContent" />
</template>
