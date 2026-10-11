/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string
  readonly VITE_MOSTRAR_USUARIOS_PRUEBA?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
