<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useAbility } from '@casl/vue'
import type { HabilidadesApp } from '@/habilidades'
import { apiAcceso } from '@/api/acceso'
import { aErrorApi } from '@/api/errores'
import { useAsincrono } from '@/composables/useAsincrono'
import { useRefConRetraso } from '@/composables/useRefConRetraso'
import { useSesionStore } from '@/stores/sesion'
import { useAvisosStore } from '@/stores/avisos'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import AlertaError from '@/components/AlertaError.vue'
import EncabezadoPagina from '@/components/EncabezadoPagina.vue'
import FormularioUsuarioDialog from './FormularioUsuarioDialog.vue'
import type { Usuario } from '@/tipos/api'

const { can } = useAbility<HabilidadesApp>()
const sesion = useSesionStore()
const avisos = useAvisosStore()

const buscar = useRefConRetraso('')
const opciones = ref({ page: 1, itemsPerPage: 20 })

const usuarios = useAsincrono(() =>
  apiAcceso.usuarios({
    buscar: buscar.value || undefined,
    page: opciones.value.page,
    por_pagina: opciones.value.itemsPerPage,
  }),
)
const roles = useAsincrono(() => apiAcceso.roles())

const columnas = [
  { title: 'Nombre', key: 'nombre', sortable: false },
  { title: 'Correo', key: 'correo', sortable: false },
  { title: 'Roles', key: 'roles', sortable: false },
  { title: 'Estado', key: 'activo', sortable: false },
  { title: '', key: 'acciones', sortable: false, align: 'end' as const },
]

watch(buscar, () => {
  opciones.value = { ...opciones.value, page: 1 }
  usuarios.ejecutar()
})
onMounted(() => {
  if (can('ver', 'Rol')) roles.ejecutar()
})

function alCambiarOpciones(nuevas: { page: number; itemsPerPage: number }) {
  opciones.value = { page: nuevas.page, itemsPerPage: nuevas.itemsPerPage }
  usuarios.ejecutar()
}

const formularioAbierto = ref(false)
const editando = ref<Usuario | null>(null)
function abrirFormulario(usuario: Usuario | null) {
  editando.value = usuario
  formularioAbierto.value = true
}
function alGuardar(usuario: Usuario) {
  avisos.exito(
    editando.value ? `Usuario ${usuario.nombre} actualizado.` : `Usuario ${usuario.nombre} creado.`,
  )
  usuarios.ejecutar()
}

const cambiandoEstado = ref<Usuario | null>(null)
const procesando = ref(false)
async function confirmarCambioEstado() {
  const usuario = cambiandoEstado.value
  if (!usuario) return
  procesando.value = true
  try {
    if (usuario.activo) await apiAcceso.desactivarUsuario(usuario.id)
    else await apiAcceso.activarUsuario(usuario.id)
    avisos.exito(
      usuario.activo
        ? `${usuario.nombre} fue desactivado y sus sesiones se cerraron.`
        : `${usuario.nombre} fue reactivado.`,
    )
    usuarios.ejecutar()
  } catch (e) {
    avisos.error(aErrorApi(e).message)
  } finally {
    procesando.value = false
    cambiandoEstado.value = null
  }
}
</script>

<template>
  <EncabezadoPagina titulo="Usuarios" subtitulo="Crea, edita, asigna roles y desactiva usuarios.">
    <template #acciones>
      <v-btn
        v-if="can('crear', 'Usuario')"
        color="primary"
        prepend-icon="mdi-account-plus"
        @click="abrirFormulario(null)"
      >
        Nuevo usuario
      </v-btn>
    </template>
  </EncabezadoPagina>

  <v-text-field
    :model-value="buscar"
    label="Buscar por nombre o correo"
    prepend-inner-icon="mdi-magnify"
    clearable
    class="mb-4"
    style="max-width: 480px"
    @update:model-value="buscar = $event ?? ''"
  />

  <AlertaError
    v-if="usuarios.error.value"
    :error="usuarios.error.value"
    reintentable
    @reintentar="usuarios.ejecutar()"
  />

  <v-card>
    <v-data-table-server
      :headers="columnas"
      :items="usuarios.datos.value?.data ?? []"
      :items-length="usuarios.datos.value?.meta.total ?? 0"
      :loading="usuarios.cargando.value"
      :page="opciones.page"
      :items-per-page="opciones.itemsPerPage"
      mobile-breakpoint="md"
      no-data-text="No hay usuarios con esa búsqueda."
      @update:options="alCambiarOpciones"
    >
      <template #[`item.roles`]="{ item }">
        <v-chip v-for="rol in item.roles" :key="rol" size="small" class="mr-1" color="secondary">{{
          rol
        }}</v-chip>
        <span v-if="!item.roles?.length" class="text-medium-emphasis">Sin rol</span>
      </template>
      <template #[`item.activo`]="{ item }">
        <v-chip :color="item.activo ? 'success' : 'default'" size="small" variant="tonal">
          {{ item.activo ? 'Activo' : 'Inactivo' }}
        </v-chip>
      </template>
      <template #[`item.acciones`]="{ item }">
        <v-btn
          v-if="can('editar', 'Usuario')"
          icon="mdi-pencil"
          variant="text"
          size="small"
          aria-label="Editar"
          @click="abrirFormulario(item)"
        />
        <v-btn
          v-if="can('desactivar', 'Usuario') && item.id !== sesion.usuario?.id"
          :icon="item.activo ? 'mdi-account-off-outline' : 'mdi-account-check-outline'"
          :color="item.activo ? 'error' : 'success'"
          variant="text"
          size="small"
          :aria-label="item.activo ? 'Desactivar' : 'Activar'"
          @click="cambiandoEstado = item"
        />
      </template>
    </v-data-table-server>
  </v-card>

  <FormularioUsuarioDialog
    v-model="formularioAbierto"
    :usuario="editando"
    :roles="(roles.datos.value ?? []).map((rol) => rol.nombre)"
    @guardado="alGuardar"
  />

  <DialogoConfirmacion
    :model-value="cambiandoEstado !== null"
    :titulo="cambiandoEstado?.activo ? 'Desactivar usuario' : 'Activar usuario'"
    :texto="
      cambiandoEstado?.activo
        ? `${cambiandoEstado?.nombre} no podrá iniciar sesión y sus sesiones abiertas se cerrarán de inmediato.`
        : `${cambiandoEstado?.nombre} podrá volver a iniciar sesión.`
    "
    :texto-confirmar="cambiandoEstado?.activo ? 'Desactivar' : 'Activar'"
    :color="cambiandoEstado?.activo ? 'error' : 'success'"
    :cargando="procesando"
    @update:model-value="!$event && (cambiandoEstado = null)"
    @confirmar="confirmarCambioEstado"
  />
</template>
