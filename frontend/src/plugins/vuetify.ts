import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import { es } from 'vuetify/locale'

export default createVuetify({
  locale: { locale: 'es', messages: { es } },
  theme: {
    defaultTheme: 'incarail',
    themes: {
      incarail: {
        dark: false,
        colors: {
          primary: '#0b3c5d',
          secondary: '#c8963e',
          accent: '#328cc1',
          background: '#f4f6f8',
          surface: '#ffffff',
          error: '#c62828',
          warning: '#ef8f00',
          info: '#1976d2',
          success: '#2e7d32',
        },
      },
    },
  },
  defaults: {
    VCard: { rounded: 'lg' },
    VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
    VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
    VAutocomplete: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
    VBtn: { rounded: 'lg' },
  },
})
