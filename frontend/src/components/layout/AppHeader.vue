<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import { removeAuth } from '../../auth'
import { useToast } from '../../composables/useToast'
import { api } from '../../api' // 🔹 Импорт API

const router = useRouter()
const route = useRoute()
const { currentUser, userInitial, isTeacherComputed, userFullName } = useAuth()
const { pushStatus } = useToast()

const emit = defineEmits(['toggle-catalog'])
const showUserMenu = ref(false)

// 🔹 Состояние для уведомлений
const unreadCount = ref(0)

// 🔹 Загрузка количества непрочитанных
async function fetchUnreadCount() {
    if (currentUser.value) {
        try {
            const res = await api.getNotifications()
            unreadCount.value = res.notifications?.filter(n => !n.is_read).length || 0
        } catch (err) {
            console.error('Failed to fetch notifications:', err)
        }
    }
}

function handleLogout() {
    removeAuth()
    pushStatus('Вы вышли из аккаунта', 'info')
    setTimeout(() => window.location.reload(), 500)
}

function goToProfile() {
    showUserMenu.value = false
    router.push('/profile')
}

function goToTeaching() {
    showUserMenu.value = false
    router.push('/teaching')
}

function goToNotifications() {
    // При клике сбрасываем счётчик визуально (точное обновление будет при возврате)
    unreadCount.value = 0
    router.push('/notifications')
}

// Загружаем уведомления при монтировании
onMounted(() => {
    fetchUnreadCount()
})
</script>

<template>
    <header class="header">
        <div class="header__container">
            <div class="header__brand" @click="router.push('/')">
                TeacherPro
            </div>

            <nav class="header__nav">
                <button class="header__link" @click="emit('toggle-catalog')">Каталог</button>
                <button class="header__link" @click="router.push('/my-learning')">Моё обучение</button>
                <button class="header__link" @click="router.push('/catalog')">Все курсы</button>
                <button v-if="isTeacherComputed" class="header__link" @click="goToTeaching">
                    Преподавание
                </button>
                <button v-if="currentUser?.role === 'admin'" class="header__link header__link--admin"
                    @click="router.push('/admin')">
                    Админ панель
                </button>
            </nav>

            <div class="header__actions">
                <template v-if="currentUser">

                    <button class="header__icon-btn" @click="goToNotifications" title="Уведомления">
                        <span class="icon">🔔</span>
                        <span v-if="unreadCount > 0" class="header__badge">{{ unreadCount }}</span>
                    </button>

                    <!-- Меню профиля -->
                    <div class="user-menu" @mouseenter="showUserMenu = true" @mouseleave="showUserMenu = false">
                        <div class="user-menu__avatar">
                            {{ userInitial }}
                        </div>

                        <Transition name="dropdown">
                            <div v-if="showUserMenu" class="user-menu__dropdown">
                                <div class="user-menu__name">{{ userFullName }}</div>
                                <div class="user-menu__divider"></div>
                                <button class="user-menu__item" @click="goToProfile">
                                    <span>👤</span> Профиль
                                </button>
                                <button class="user-menu__item" @click="router.push('/profile/edit')">
                                    <span>⚙</span> Настройки
                                </button>
                                <button v-if="isTeacherComputed" class="user-menu__item" @click="goToTeaching">
                                    <span>🕮</span> Преподавание
                                </button>
                                <div class="user-menu__divider"></div>
                                <button class="user-menu__item user-menu__logout" @click="handleLogout">
                                    <span>🚪</span> Выйти
                                </button>
                            </div>
                        </Transition>
                    </div>
                </template>

                <template v-else>
                    <button class="header__btn header__btn--ghost" @click="router.push('/?auth=login')">
                        Войти
                    </button>
                    <button class="header__btn header__btn--primary" @click="router.push('/?auth=register')">
                        Регистрация
                    </button>
                </template>
            </div>
        </div>
    </header>
</template>

<style scoped>
/* === Базовые стили (ваши) === */
.header {
    background: #000000;
    color: white;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
}

.header__container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    height: 64px;
    display: flex;
    align-items: center;
    gap: 32px;
}

.header__brand {
    font-size: 24px;
    font-weight: 800;
    cursor: pointer;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.header__nav {
    display: flex;
    gap: 24px;
}

.header__link {
    background: none;
    border: none;
    color: #a0aec0;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.2s;
    padding: 0;
}

.header__link:hover {
    color: white;
}

.header__link--admin {
    color: #f6ad55;
}

.header__link--admin:hover {
    color: #ed8936;
}

.header__actions {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header__btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.header__btn--ghost {
    background: transparent;
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.header__btn--ghost:hover {
    background: rgba(255, 255, 255, 0.1);
}

.header__btn--primary {
    background: #ffffff;
    color: #000000;
}

.header__btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

/* === Стили для кнопки уведомлений (НОВЫЕ) === */
.header__icon-btn {
    position: relative;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    padding: 8px;
    margin-right: 4px;
    color: #a0aec0;
    transition: color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header__icon-btn:hover {
    color: white;
}

.header__badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #e53e3e;
    color: white;
    font-size: 10px;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid #000000;
    line-height: 1;
}

/* === Стили меню профиля (ваши) === */
.user-menu {
    position: relative;
}

.user-menu__avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #ffffff;
    color: #000000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: transform 0.2s;
}

.user-menu__avatar:hover {
    transform: scale(1.05);
}

.user-menu__dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    min-width: 240px;
    overflow: hidden;
    color: #2d3748;
    z-index: 1001;
}

.user-menu__name {
    padding: 16px;
    font-weight: 600;
    font-size: 14px;
    color: #1a202c;
    border-bottom: 1px solid #e2e8f0;
}

.user-menu__divider {
    height: 1px;
    background: #e2e8f0;
    margin: 4px 0;
}

.user-menu__item {
    width: 100%;
    padding: 12px 16px;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #4a5568;
    transition: background 0.2s;
}

.user-menu__item:hover {
    background: #f7fafc;
    color: #2d3748;
}

.user-menu__logout {
    color: #e53e3e;
}

.user-menu__logout:hover {
    background: #fff5f5;
}

.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>