<template>
  <v-empty-state
    icon="mdi-map-search-outline"
    title="Página no encontrada"
    text="La dirección no existe."
    class="my-12"
  >
    <template #actions>
      <v-btn color="primary" to="/">Volver al inicio</v-btn>
    </template>
  </v-empty-state>
</template>
