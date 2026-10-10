<script setup lang="ts">
import { computed, watch } from 'vue'
import type { ErrorApi } from '@/api/errores'
import { useCuentaRegresiva } from '@/composables/useCuentaRegresiva'

const props = defineProps<{ error: ErrorApi; reintentable?: boolean }>()
const emit = defineEmits<{ reintentar: [] }>()

const cuenta = useCuentaRegresiva()

// Tras un límite de peticiones, reintenta solo cuando vuelve a haber cupo.
watch(
  () => props.error,
  (error) => {
    if (error.esLimiteAlcanzado && error.reintentarEn) {
      cuenta.iniciar(error.reintentarEn + 1, () => props.reintentable && emit('reintentar'))
    } else {
      cuenta.detener()
    }
  },
  { immediate: true },
)

const aspecto = computed(() => {
  const { error } = props
  if (error.esLimiteAlcanzado)
    return {
      tipo: 'warning',
      icono: 'mdi-timer-sand',
      titulo: 'Límite de consultas alcanzado',
    } as const
  if (error.esProveedorCaido)
    return {
      tipo: 'error',
      icono: 'mdi-cloud-off-outline',
      titulo: 'football-data.org no está disponible',
    } as const
  if (error.estado === 403)
    return { tipo: 'warning', icono: 'mdi-lock-outline', titulo: 'Sin permiso' } as const
  if (error.estado === 404)
    return { tipo: 'info', icono: 'mdi-help-circle-outline', titulo: 'No encontrado' } as const
  if (error.codigo === 'sin_conexion')
    return {
      tipo: 'error',
      icono: 'mdi-lan-disconnect',
      titulo: 'Sin conexión con la API',
    } as const
  return { tipo: 'error', icono: 'mdi-alert-circle-outline', titulo: 'Algo salió mal' } as const
})
</script>

<template>
  <v-alert :type="aspecto.tipo" :icon="aspecto.icono" variant="tonal" border="start" class="mb-4">
    <template #title>{{ aspecto.titulo }}</template>
    <div>{{ error.message }}</div>
    <div v-if="cuenta.activa.value" class="text-body-2 mt-1">
      <template v-if="reintentable"
        >Reintentaremos automáticamente en {{ cuenta.restantes.value }} s.</template
      >
      <template v-else>Podrás reintentar en {{ cuenta.restantes.value }} s.</template>
    </div>
    <template v-if="reintentable" #append>
      <v-btn
        variant="outlined"
        size="small"
        :disabled="cuenta.activa.value"
        @click="emit('reintentar')"
      >
        Reintentar
      </v-btn>
    </template>
  </v-alert>
</template>
