import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { abilitiesPlugin } from '@casl/vue'

import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { ability } from './ability'
import { sessionHooks } from './api/http'
import { useAuthStore } from './stores/auth'
import { useNotifyStore } from './stores/notify'
import './styles.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)
app.use(abilitiesPlugin, ability, { useGlobalProperties: true })

// When the API says the session is gone, clean up and go to the login page
// with a clear message instead of leaving a broken screen.
const endSession = (message: string) => {
  const auth = useAuthStore()
  if (!auth.token) return
  auth.clear()
  useNotifyStore().warning(message)
  const current = router.currentRoute.value
  router.push({ name: 'login', query: current.meta.public ? {} : { redirect: current.fullPath } })
}
sessionHooks.onUnauthenticated = endSession
sessionHooks.onAccountDisabled = endSession

app.mount('#app')
