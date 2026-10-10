<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    text: string
    confirmText?: string
    color?: string
    loading?: boolean
  }>(),
  { confirmText: 'Confirmar', color: 'primary', loading: false },
)

const open = defineModel<boolean>({ required: true })
defineEmits<{ confirm: [] }>()
</script>

<template>
  <v-dialog v-model="open" max-width="440">
    <v-card :title="title" :text="text">
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Cancelar</v-btn>
        <v-btn :color="color" variant="flat" :loading="loading" @click="$emit('confirm')">
          {{ confirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
