import { createRouter, createWebHistory } from 'vue-router'
import { ability, type Action, type Subject } from '@/ability'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    /** Public pages (login) skip the session check. */
    public?: boolean
    /** CASL rule required to open the page. */
    can?: [Action, Subject]
    title?: string
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true, title: 'Iniciar sesión' },
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      children: [
        { path: '', name: 'home', redirect: { name: 'competitions' } },
        {
          path: 'competitions',
          name: 'competitions',
          component: () => import('@/views/CompetitionsView.vue'),
          meta: { can: ['read', 'Competition'], title: 'Ligas' },
        },
        {
          path: 'competitions/:id',
          name: 'competition',
          component: () => import('@/views/CompetitionDetailView.vue'),
          props: true,
          meta: { can: ['read', 'Competition'], title: 'Liga' },
        },
        {
          path: 'teams',
          name: 'teams',
          component: () => import('@/views/TeamsView.vue'),
          meta: { can: ['read', 'Team'], title: 'Equipos' },
        },
        {
          path: 'teams/:id',
          name: 'team',
          component: () => import('@/views/TeamDetailView.vue'),
          props: true,
          meta: { can: ['read', 'Team'], title: 'Equipo' },
        },
        {
          path: 'players',
          name: 'players',
          component: () => import('@/views/PlayersView.vue'),
          meta: { can: ['read', 'Player'], title: 'Jugadores' },
        },
        {
          path: 'admin/users',
          name: 'users',
          component: () => import('@/views/admin/UsersView.vue'),
          meta: { can: ['read', 'User'], title: 'Usuarios' },
        },
        {
          path: 'admin/roles',
          name: 'roles',
          component: () => import('@/views/admin/RolesView.vue'),
          meta: { can: ['read', 'Role'], title: 'Roles y permisos' },
        },
        {
          path: 'forbidden',
          name: 'forbidden',
          component: () => import('@/views/ForbiddenView.vue'),
          meta: { title: 'Sin permiso' },
        },
        {
          path: ':pathMatch(.*)*',
          name: 'not-found',
          component: () => import('@/views/NotFoundView.vue'),
          meta: { title: 'No encontrado' },
        },
      ],
    },
  ],
})

/** First page the user may open, depending on their abilities. */
export function homeFor(): { name: string } {
  const candidates: [string, Subject][] = [
    ['competitions', 'Competition'],
    ['teams', 'Team'],
    ['players', 'Player'],
    ['users', 'User'],
    ['roles', 'Role'],
  ]
  const match = candidates.find(([, subject]) => ability.can('read', subject))
  return { name: match ? match[0] : 'forbidden' }
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.restore()

  if (to.meta.public) {
    return auth.isAuthenticated ? homeFor() : true
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  }

  if (to.name === 'competitions' && ability.cannot('read', 'Competition')) {
    return homeFor()
  }

  if (to.meta.can && ability.cannot(...to.meta.can)) {
    return { name: 'forbidden', query: { from: to.fullPath } }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Inca Rail Fútbol` : 'Inca Rail Fútbol'
})

export default router
