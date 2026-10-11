import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { abilitiesPlugin } from '@casl/vue'

import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { habilidades } from './habilidades'
import { ganchosSesion } from './api/cliente'
import { useSesionStore } from './stores/sesion'
import { useAvisosStore } from './stores/avisos'
import './estilos.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)
app.use(abilitiesPlugin, habilidades, { useGlobalProperties: true })

// Cuando la API dice que la sesión ya no vale, se limpia y se vuelve al
// inicio de sesión con un mensaje claro, en vez de dejar una pantalla rota.
const terminarSesion = (mensaje: string) => {
  const sesion = useSesionStore()
  if (!sesion.token) return
  sesion.limpiar()
  useAvisosStore().advertencia(mensaje)
  const actual = router.currentRoute.value
  router.push({
    name: 'iniciar-sesion',
    query: actual.meta.publica ? {} : { volver: actual.fullPath },
  })
}
ganchosSesion.alPerderSesion = terminarSesion
ganchosSesion.alDesactivarCuenta = terminarSesion

// Tras publicar una versión nueva, una pestaña abierta pide pantallas que ya no
// existen: se recarga una vez en la ruta destino para tomar la versión nueva.
router.onError((error, destino) => {
  const esModuloPerdido = /dynamically imported module|Importing a module script failed/.test(
    String(error?.message),
  )
  if (!esModuloPerdido || sessionStorage.getItem('recarga-por-version')) return
  sessionStorage.setItem('recarga-por-version', '1')
  window.location.assign(destino.fullPath)
})
router.afterEach(() => sessionStorage.removeItem('recarga-por-version'))

app.mount('#app')
