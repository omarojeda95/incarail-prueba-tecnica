import { ref } from 'vue'
import { defineStore } from 'pinia'

export interface Notice {
  id: number
  text: string
  color: 'success' | 'info' | 'warning' | 'error'
  timeout: number
}

/** Global snackbar queue. */
export const useNotifyStore = defineStore('notify', () => {
  const notices = ref<Notice[]>([])
  let nextId = 1

  function push(text: string, color: Notice['color'] = 'info', timeout = 5000): void {
    notices.value.push({ id: nextId++, text, color, timeout })
  }

  function dismiss(id: number): void {
    notices.value = notices.value.filter((notice) => notice.id !== id)
  }

  return {
    notices,
    dismiss,
    success: (text: string) => push(text, 'success'),
    info: (text: string) => push(text, 'info'),
    warning: (text: string) => push(text, 'warning', 8000),
    error: (text: string) => push(text, 'error', 8000),
  }
})
