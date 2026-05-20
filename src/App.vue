<script setup>
import { ref, onMounted } from 'vue'
import { RouterView } from 'vue-router'
import AppHeader from './components/layout/AppHeader.vue'
import Toast from './components/ui/Toast.vue'
import AuthModal from './components/common/AuthModal.vue'
import CatalogModal from './components/common/CatalogModal.vue'
import { useAuth } from './composables/useAuth'
import { loadUser, storeAuth, loadToken } from './auth' 
import { api } from './api'

const { updateUser } = useAuth()

const showAuthModal = ref(false)
const showCatalog = ref(false)

function toggleCatalog() {
  showCatalog.value = !showCatalog.value
}

onMounted(async () => {
  // 🔹 ИСПРАВЛЕНИЕ: Используем loadToken вместо прямого доступа к localStorage
  const token = loadToken()
  const user = loadUser()

  if (token && user) {
    try {
      const res = await api.me()
      if (res.user) {
        // Теперь storeAuth получит валидный токен
        storeAuth(token, res.user) 
        updateUser(res.user)
      }
    } catch {
      console.warn('Using cached user data')
    }
  } else if (!token && user) {
    // Если есть юзер, но нет токена (или токен истек) — чистим
    localStorage.removeItem('auth_user') 
  }
})
</script>
<template>
  <div class="app">
    <AppHeader @toggle-catalog="toggleCatalog" />

    <main class="main">
      <RouterView />
    </main>

    <Toast />
    <AuthModal v-model="showAuthModal" />

    <CatalogModal v-if="showCatalog" @close="showCatalog = false" />
  </div>
</template>

<style>
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #f7fafc;
  color: #2d3748;
  line-height: 1.5;
}

.app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.main {
  flex: 1;
}

/* Скроллбар */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}
</style>