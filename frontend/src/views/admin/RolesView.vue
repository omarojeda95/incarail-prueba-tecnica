<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAbility } from '@casl/vue'
import type { HabilidadesApp } from '@/habilidades'
import { apiAcceso } from '@/api/acceso'
import { ErrorApi, aErrorApi } from '@/api/errores'
import { useAsincrono } from '@/composables/useAsincrono'
import { useAvisosStore } from '@/stores/avisos'
import EstadoAsincrono from '@/components/EstadoAsincrono.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import EncabezadoPagina from '@/components/EncabezadoPagina.vue'
import type { Permiso, Rol } from '@/tipos/api'

const { can } = useAbility<HabilidadesApp>()
const avisos = useAvisosStore()

const roles = useAsincrono(() => apiAcceso.roles())
const permisos = useAsincrono(() => apiAcceso.permisos())
onMounted(() => Promise.all([roles.ejecutar(), permisos.ejecutar()]))

const SUJETOS: Record<string, string> = {
  Liga: 'Ligas',
  Equipo: 'Equipos',
  Jugador: 'Jugadores',
  Usuario: 'Usuarios',
  Rol: 'Roles',
}

const grupos = computed(() => {
  const mapa = new Map<string, Permiso[]>()
  for (const permiso of permisos.datos.value ?? []) {
    mapa.set(permiso.sujeto, [...(mapa.get(permiso.sujeto) ?? []), permiso])
  }
  return [...mapa.entries()].map(([sujeto, lista]) => ({
    sujeto,
    etiqueta: SUJETOS[sujeto] ?? sujeto,
    lista,
  }))
})
const describir = (nombre: string) =>
  permisos.datos.value?.find((p) => p.nombre === nombre)?.descripcion ?? nombre

const editorAbierto = ref(false)
const editando = ref<Rol | null>(null)
const formulario = ref({ nombre: '', permisos: [] as string[] })
const guardando = ref(false)
const errorFormulario = ref<ErrorApi | null>(null)

function abrirEditor(rol: Rol | null) {
  editando.value = rol
  formulario.value = { nombre: rol?.nombre ?? '', permisos: [...(rol?.permisos ?? [])] }
  errorFormulario.value = null
  editorAbierto.value = true
}

async function guardar() {
  guardando.value = true
  errorFormulario.value = null
  try {
    if (editando.value) await apiAcceso.editarRol(editando.value.id, formulario.value)
    else await apiAcceso.crearRol(formulario.value)
    avisos.exito(`Rol ${formulario.value.nombre} guardado.`)
    editorAbierto.value = false
    roles.ejecutar()
  } catch (e) {
    errorFormulario.value = aErrorApi(e)
  } finally {
    guardando.value = false
  }
}

const eliminando = ref<Rol | null>(null)
const procesando = ref(false)
async function confirmarEliminacion() {
  if (!eliminando.value) return
  procesando.value = true
  try {
    await apiAcceso.eliminarRol(eliminando.value.id)
    avisos.exito(`Rol ${eliminando.value.nombre} eliminado.`)
    roles.ejecutar()
  } catch (e) {
    avisos.error(aErrorApi(e).message)
  } finally {
    procesando.value = false
    eliminando.value = null
  }
}
</script>

<template>
  <EncabezadoPagina
    titulo="Roles y permisos"
    subtitulo="Cada rol agrupa permisos. El servidor valida cada permiso; la interfaz solo se adapta a ellos."
  >
    <template #acciones>
      <v-btn
        v-if="can('gestionar', 'Rol')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="abrirEditor(null)"
      >
        Nuevo rol
      </v-btn>
    </template>
  </EncabezadoPagina>

  <EstadoAsincrono
    :cargando="roles.cargando.value"
    :error="roles.error.value ?? permisos.error.value"
    :vacio="roles.datos.value?.length === 0"
    @reintentar="roles.ejecutar()"
  >
    <v-row>
      <v-col v-for="rol in roles.datos.value" :key="rol.id" cols="12" md="6" xl="4">
        <v-card height="100%">
          <v-card-item>
            <v-card-title class="d-flex align-center ga-2">
              {{ rol.nombre }}
              <v-chip v-if="rol.protegido" size="x-small" prepend-icon="mdi-lock">sistema</v-chip>
            </v-card-title>
            <v-card-subtitle
              >{{ rol.cantidad_usuarios }} usuario(s) ·
              {{ rol.permisos.length }} permiso(s)</v-card-subtitle
            >
            <template v-if="can('gestionar', 'Rol') && !rol.protegido" #append>
              <v-btn
                icon="mdi-pencil"
                variant="text"
                size="small"
                aria-label="Editar rol"
                @click="abrirEditor(rol)"
              />
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                color="error"
                aria-label="Eliminar rol"
                @click="eliminando = rol"
              />
            </template>
          </v-card-item>
          <v-card-text>
            <v-chip
              v-for="permiso in rol.permisos"
              :key="permiso"
              size="small"
              variant="tonal"
              color="primary"
              class="mr-1 mb-1"
              :title="describir(permiso)"
            >
              {{ permiso }}
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </EstadoAsincrono>

  <v-dialog v-model="editorAbierto" max-width="640" scrollable>
    <v-card :title="editando ? `Editar rol ${editando.nombre}` : 'Nuevo rol'">
      <v-card-text>
        <v-alert
          v-if="errorFormulario && errorFormulario.estado !== 422"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ errorFormulario.message }}
        </v-alert>
        <v-text-field
          v-model="formulario.nombre"
          label="Nombre del rol"
          hint="Minúsculas, números, guiones"
          persistent-hint
          :error-messages="errorFormulario?.campo('nombre')"
          class="mb-4"
        />
        <div v-for="grupo in grupos" :key="grupo.sujeto" class="mb-3">
          <div class="text-subtitle-2 mb-1">{{ grupo.etiqueta }}</div>
          <v-checkbox
            v-for="permiso in grupo.lista"
            :key="permiso.nombre"
            v-model="formulario.permisos"
            :value="permiso.nombre"
            density="compact"
            hide-details
          >
            <template #label>
              <div>
                <div>{{ permiso.descripcion }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ permiso.nombre }} · CASL: {{ permiso.accion }} / {{ permiso.sujeto }}
                </div>
              </div>
            </template>
          </v-checkbox>
        </div>
        <div v-if="errorFormulario?.campo('permisos')" class="text-error text-caption">
          {{ errorFormulario.campo('permisos') }}
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="editorAbierto = false">Cancelar</v-btn>
        <v-btn color="primary" variant="flat" :loading="guardando" @click="guardar">Guardar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <DialogoConfirmacion
    :model-value="eliminando !== null"
    titulo="Eliminar rol"
    :texto="`¿Eliminar el rol ${eliminando?.nombre}? Esta acción no se puede deshacer.`"
    texto-confirmar="Eliminar"
    color="error"
    :cargando="procesando"
    @update:model-value="!$event && (eliminando = null)"
    @confirmar="confirmarEliminacion"
  />
</template>
