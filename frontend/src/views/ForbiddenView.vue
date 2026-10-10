<script setup lang="ts">
import { homeFor } from '@/router'
</script>

<template>
  <v-empty-state
    icon="mdi-lock-outline"
    title="No tienes permiso para ver esta página"
    text="Tu rol no incluye el permiso necesario. Si crees que es un error, pide a un administrador que lo revise."
    class="my-12"
  >
    <template #actions>
      <v-btn color="primary" :to="homeFor()">Ir al inicio</v-btn>
    </template>
  </v-empty-state>
</template>
