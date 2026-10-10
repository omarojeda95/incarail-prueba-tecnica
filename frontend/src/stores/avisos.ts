import { ref } from 'vue'
import { defineStore } from 'pinia'

export interface Aviso {
  id: number
  texto: string
  color: 'success' | 'info' | 'warning' | 'error'
  duracion: number
}

/** Cola global de avisos (snackbar). */
export const useAvisosStore = defineStore('avisos', () => {
  const avisos = ref<Aviso[]>([])
  let siguienteId = 1

  function agregar(texto: string, color: Aviso['color'] = 'info', duracion = 5000): void {
    avisos.value.push({ id: siguienteId++, texto, color, duracion })
  }

  function descartar(id: number): void {
    avisos.value = avisos.value.filter((aviso) => aviso.id !== id)
  }

  return {
    avisos,
    descartar,
    exito: (texto: string) => agregar(texto, 'success'),
    info: (texto: string) => agregar(texto, 'info'),
    advertencia: (texto: string) => agregar(texto, 'warning', 8000),
    error: (texto: string) => agregar(texto, 'error', 8000),
  }
})
