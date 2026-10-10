<script setup lang="ts">
import type { ErrorApi } from '@/api/errores'
import AlertaError from './AlertaError.vue'

/**
 * Envuelve cualquier bloque de datos con los tres estados que toda pantalla
 * necesita: cargando (esqueleto), error (con reintento) y vacío (con una pista).
 */
withDefaults(
  defineProps<{
    cargando: boolean
    error: ErrorApi | null
    vacio?: boolean
    tituloVacio?: string
    textoVacio?: string
    iconoVacio?: string
    esqueleto?: string
    /** Mantener el contenido visible mientras se recarga (p. ej. al paginar). */
    mantenerContenido?: boolean
  }>(),
  {
    vacio: false,
    tituloVacio: 'No hay nada que mostrar',
    textoVacio: '',
    iconoVacio: 'mdi-inbox-outline',
    esqueleto: 'card, card, card',
    mantenerContenido: false,
  },
)

defineEmits<{ reintentar: [] }>()
</script>

<template>
  <AlertaError v-if="error" :error="error" reintentable @reintentar="$emit('reintentar')" />

  <v-skeleton-loader
    v-if="cargando && !mantenerContenido"
    :type="esqueleto"
    class="bg-transparent"
  />

  <v-empty-state
    v-else-if="vacio && !error"
    :icon="iconoVacio"
    :title="tituloVacio"
    :text="textoVacio"
    class="my-8"
  >
    <template v-if="$slots['acciones-vacio']" #actions>
      <slot name="acciones-vacio" />
    </template>
  </v-empty-state>

  <slot v-else-if="!error || mantenerContenido" />
</template>
