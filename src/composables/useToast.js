// frontend/src/composables/useToast.js
import { ref } from 'vue'

// Хранилище уведомлений
const notifications = ref([])

// Функция добавления уведомления
export function useToast() {
  const pushStatus = (message, type = 'info', duration = 3000) => {
    const id = Date.now() + Math.random()
    notifications.value.push({ id, message, type })
    
    // Автоматическое удаление через указанное время
    setTimeout(() => {
      removeStatus(id)
    }, duration)
  }

  const removeStatus = (id) => {
    notifications.value = notifications.value.filter(n => n.id !== id)
  }

  return {
    notifications,
    pushStatus,
    removeStatus
  }
}