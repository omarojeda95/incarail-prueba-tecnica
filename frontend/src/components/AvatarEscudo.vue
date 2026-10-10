<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(
  defineProps<{ src?: string | null; nombre: string; tamano?: number | string }>(),
  { src: null, tamano: 40 },
)

const fallo = ref(false)
const iniciales = computed(
  () =>
    props.nombre
      .split(/\s+/)
      .filter((palabra) => palabra.length > 2)
      .slice(0, 2)
      .map((palabra) => palabra[0]?.toUpperCase())
      .join('') || props.nombre.slice(0, 2).toUpperCase(),
)
</script>

<template>
  <v-avatar :size="tamano" :color="src && !fallo ? 'transparent' : 'primary'" rounded="lg">
    <v-img v-if="src && !fallo" :src="src" :alt="nombre" contain @error="fallo = true" />
    <span v-else class="text-caption font-weight-bold">{{ iniciales }}</span>
  </v-avatar>
</template>
