import { ref, computed } from 'vue'
import { loadUser, isAuthenticated, isTeacher } from '../auth'

export function useAuth() {
  const currentUser = ref(loadUser())

  const userFullName = computed(() => {
    if (!currentUser.value) return ''
    const first = currentUser.value.first_name || ''
    const last = currentUser.value.last_name || ''
    return `${first} ${last}`.trim() || 'Пользователь'
  })

  const userInitial = computed(() => {
    if (!currentUser.value) return 'U'
    const first = currentUser.value.first_name?.charAt(0)?.toUpperCase() || ''
    const last = currentUser.value.last_name?.charAt(0)?.toUpperCase() || ''
    return (first + last) || 'U'
  })

  const isTeacherComputed = computed(() => {
    return isTeacher() || currentUser.value?.is_teacher === true || currentUser.value?.role === 'teacher'
  })

  function updateUser(user) {
    currentUser.value = user
  }

  function clearUser() {
    currentUser.value = null
  }

  return {
    currentUser,
    userFullName,
    userInitial,
    isTeacherComputed,
    updateUser,
    clearUser,
    isAuthenticated: () => isAuthenticated()
  }
}