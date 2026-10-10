<script setup lang="ts">
withDefaults(
  defineProps<{
    titulo: string
    texto: string
    textoConfirmar?: string
    color?: string
    cargando?: boolean
  }>(),
  { textoConfirmar: 'Confirmar', color: 'primary', cargando: false },
)

const abierto = defineModel<boolean>({ required: true })
defineEmits<{ confirmar: [] }>()
</script>

<template>
  <v-dialog v-model="abierto" max-width="440">
    <v-card :title="titulo" :text="texto">
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="abierto = false">Cancelar</v-btn>
        <v-btn :color="color" variant="flat" :loading="cargando" @click="$emit('confirmar')">
          {{ textoConfirmar }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
