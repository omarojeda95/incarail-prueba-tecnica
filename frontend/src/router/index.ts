import { createRouter, createWebHistory } from 'vue-router'
import { habilidades, type Accion, type Sujeto } from '@/habilidades'
import { useSesionStore } from '@/stores/sesion'

declare module 'vue-router' {
  interface RouteMeta {
    /** Páginas públicas (inicio de sesión) que no exigen sesión. */
    publica?: boolean
    /** Regla CASL necesaria para abrir la página. */
    requiere?: [Accion, Sujeto]
    titulo?: string
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/iniciar-sesion',
      name: 'iniciar-sesion',
      component: () => import('@/views/IniciarSesionView.vue'),
      meta: { publica: true, titulo: 'Iniciar sesión' },
    },
    {
      path: '/',
      component: () => import('@/layouts/PlantillaAplicacion.vue'),
      children: [
        { path: '', name: 'inicio', redirect: { name: 'ligas' } },
        {
          path: 'ligas',
          name: 'ligas',
          component: () => import('@/views/LigasView.vue'),
          meta: { requiere: ['ver', 'Liga'], titulo: 'Ligas' },
        },
        {
          path: 'ligas/:id',
          name: 'liga',
          component: () => import('@/views/DetalleLigaView.vue'),
          props: true,
          meta: { requiere: ['ver', 'Liga'], titulo: 'Liga' },
        },
        {
          path: 'equipos',
          name: 'equipos',
          component: () => import('@/views/EquiposView.vue'),
          meta: { requiere: ['ver', 'Equipo'], titulo: 'Equipos' },
        },
        {
          path: 'equipos/:id',
          name: 'equipo',
          component: () => import('@/views/DetalleEquipoView.vue'),
          props: true,
          meta: { requiere: ['ver', 'Equipo'], titulo: 'Equipo' },
        },
        {
          path: 'jugadores',
          name: 'jugadores',
          component: () => import('@/views/JugadoresView.vue'),
          meta: { requiere: ['ver', 'Jugador'], titulo: 'Jugadores' },
        },
        {
          path: 'admin/usuarios',
          name: 'usuarios',
          component: () => import('@/views/admin/UsuariosView.vue'),
          meta: { requiere: ['ver', 'Usuario'], titulo: 'Usuarios' },
        },
        {
          path: 'admin/roles',
          name: 'roles',
          component: () => import('@/views/admin/RolesView.vue'),
          meta: { requiere: ['ver', 'Rol'], titulo: 'Roles y permisos' },
        },
        {
          path: 'sin-permiso',
          name: 'sin-permiso',
          component: () => import('@/views/SinPermisoView.vue'),
          meta: { titulo: 'Sin permiso' },
        },
        {
          path: ':pathMatch(.*)*',
          name: 'no-encontrado',
          component: () => import('@/views/NoEncontradoView.vue'),
          meta: { titulo: 'No encontrado' },
        },
      ],
    },
  ],
})

/** Primera página que el usuario puede abrir según sus habilidades. */
export function paginaInicial(): { name: string } {
  const candidatas: [string, Sujeto][] = [
    ['ligas', 'Liga'],
    ['equipos', 'Equipo'],
    ['jugadores', 'Jugador'],
    ['usuarios', 'Usuario'],
    ['roles', 'Rol'],
  ]
  const encontrada = candidatas.find(([, sujeto]) => habilidades.can('ver', sujeto))
  return { name: encontrada ? encontrada[0] : 'sin-permiso' }
}

router.beforeEach(async (destino) => {
  const sesion = useSesionStore()
  await sesion.restaurar()

  if (destino.meta.publica) {
    return sesion.autenticado ? paginaInicial() : true
  }

  if (!sesion.autenticado) {
    return {
      name: 'iniciar-sesion',
      query: destino.fullPath !== '/' ? { volver: destino.fullPath } : {},
    }
  }

  if (destino.name === 'ligas' && habilidades.cannot('ver', 'Liga')) {
    return paginaInicial()
  }

  if (destino.meta.requiere && habilidades.cannot(...destino.meta.requiere)) {
    return { name: 'sin-permiso', query: { desde: destino.fullPath } }
  }

  return true
})

router.afterEach((destino) => {
  document.title = destino.meta.titulo
    ? `${destino.meta.titulo} · Inca Rail Fútbol`
    : 'Inca Rail Fútbol'
})

export default router
