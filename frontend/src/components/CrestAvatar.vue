<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(
  defineProps<{ src?: string | null; name: string; size?: number | string }>(),
  {
    src: null,
    size: 40,
  },
)

const failed = ref(false)
const initials = computed(
  () =>
    props.name
      .split(/\s+/)
      .filter((word) => word.length > 2)
      .slice(0, 2)
      .map((word) => word[0]?.toUpperCase())
      .join('') || props.name.slice(0, 2).toUpperCase(),
)
</script>

<template>
  <v-avatar :size="size" :color="src && !failed ? 'transparent' : 'primary'" rounded="lg">
    <v-img v-if="src && !failed" :src="src" :alt="name" contain @error="failed = true" />
    <span v-else class="text-caption font-weight-bold">{{ initials }}</span>
  </v-avatar>
</template>
