<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { pushStatus } = useToast()
const { currentUser } = useAuth()

const notifications = ref([])
const isLoading = ref(true)

// Фильтры
const filterType = ref('all') // 'all' | 'certificate' | 'course'
const filterStatus = ref('all') // 'all' | 'unread'
const searchQuery = ref('')

// Загрузка данных
async function loadNotifications() {
    isLoading.value = true
    try {
        const res = await api.getNotifications()
        notifications.value = res.notifications || []
    } catch (err) {
        pushStatus('Ошибка загрузки уведомлений', 'error')
    } finally {
        isLoading.value = false
    }
}

// Логика фильтров
const filteredNotifications = computed(() => {
    return notifications.value.filter(n => {
        // 1. Фильтр по статусу (прочитано/нет)
        if (filterStatus.value === 'unread' && n.is_read) return false

        // 2. Фильтр по категории
        if (filterType.value !== 'all') {
            if (filterType.value === 'certificate' && n.type !== 'certificate') return false
            if (filterType.value === 'course' && (n.type !== 'course_approved' && n.type !== 'course_rejected')) return false
        }

        // 3. Поиск
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase()
            const matchText = n.title.toLowerCase().includes(query) ||
                n.message.toLowerCase().includes(query) ||
                (n.course_name && n.course_name.toLowerCase().includes(query))
            if (!matchText) return false
        }

        return true
    })
})

// Действия
async function markAsRead(id) {
    try {
        await api.markNotificationAsRead(id)
        // Обновляем локально без перезагрузки
        const n = notifications.value.find(item => item.id === id)
        if (n) n.is_read = true
    } catch (err) {
        console.error(err)
    }
}

async function markAllAsRead() {
    try {
        await api.markAllNotificationsAsRead()
        notifications.value.forEach(n => n.is_read = true)
        pushStatus('Все уведомления отмечены как прочитанные', 'success')
    } catch (err) {
        console.error(err)
    }
}

// Вспомогательные функции
function getIcon(type) {
    if (type === 'certificate') return '🏆'
    if (type === 'course_approved') return '✅'
    if (type === 'course_rejected') return '❌'
    return '🔔'
}

function getCourseIcon(name) {
    // Заглушка для иконки курса (можно заменить на реальную логику)
    return name && name.toLowerCase().includes('kotlin') ? 'K' : '📚'
}

function timeAgo(dateString) {
    const date = new Date(dateString)
    const now = new Date()
    const diffTime = Math.abs(now - date)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    if (diffDays === 0) return 'Сегодня'
    if (diffDays === 1) return 'Вчера'
    if (diffDays < 7) return `${diffDays} дней назад`
    return date.toLocaleDateString('ru-RU')
}

onMounted(() => {
    if (!currentUser.value) {
        router.push('/auth/login')
        return
    }
    loadNotifications()
})
</script>

<template>
    <div class="notifications-page">
        <div class="notifications__container">

            <h1 class="page-title">Уведомления</h1>

            <!-- 🔹 Панель фильтров -->
            <div class="notifications__toolbar">
                <div class="toolbar__filters">
                    <select v-model="filterStatus" class="filter-select">
                        <option value="all">Все статусы</option>
                        <option value="unread">🔵 Непрочитанные</option>
                    </select>

                    <select v-model="filterType" class="filter-select">
                        <option value="all">Все категории</option>
                        <option value="certificate">🏆 Сертификаты</option>
                        <option value="course">📚 Курсы</option>
                    </select>
                </div>

                <div class="toolbar__actions">
                    <input v-model="searchQuery" type="text" placeholder="🔍 Поиск по названию..."
                        class="search-input" />
                    <button class="mark-all-btn" @click="markAllAsRead" :disabled="isLoading">
                        Отметить все как прочитанные
                    </button>
                </div>
            </div>

            <!-- 🔹 Список уведомлений -->
            <div class="notifications__list" v-if="!isLoading">

                <div v-if="filteredNotifications.length === 0" class="empty-state">
                    <span class="empty-icon"></span>
                    <p>Нет уведомлений, соответствующих фильтрам</p>
                </div>

                <div v-for="n in filteredNotifications" :key="n.id" class="notification-card"
                    :class="{ 'is-unread': !n.is_read }" @click="!n.is_read && markAsRead(n.id)">
                    <!-- Левая часть: Иконка и статус -->
                    <div class="notification__indicator">
                        <div class="status-dot" :class="{ 'is-read': n.is_read }"></div>
                        <span class="notification__icon">{{ getIcon(n.type) }}</span>
                    </div>

                    <!-- Центральная часть: Текст -->
                    <div class="notification__content">
                        <div class="notification__header">
                            <h3 class="notification__title">{{ n.title }}</h3>
                            <span class="notification__time">{{ timeAgo(n.created_at) }}</span>
                        </div>

                        <p class="notification__message">{{ n.message }}</p>

                        <div class="notification__footer">
                            <span v-if="n.course_name" class="course-tag">
                                <span class="course-icon">{{ getCourseIcon(n.course_name) }}</span>
                                {{ n.course_name }}
                            </span>

                            <div class="notification__actions" @click.stop>
                                <!-- 🔹 Сертификат → Переход в профиль -->
                                <button v-if="n.type === 'certificate'" class="action-btn link"
                                    @click="router.push('/profile')">
                                    Посмотреть сертификат
                                </button>

                                <!-- 🔹 Отклонённый курс → Переход в преподавание -->
                                <button v-if="n.type === 'course_rejected'" class="action-btn link"
                                    @click="router.push('/teaching')">
                                    Редактировать курс
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="loading-skeleton">
                <div v-for="i in 3" :key="i" class="skeleton-card"></div>
            </div>

        </div>
    </div>
</template>

<style scoped>
.notifications-page {
    background: #f7fafc;
    min-height: 100vh;
    padding: 32px 16px;
}

.notifications__container {
    max-width: 800px;
    margin: 0 auto;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 24px;
}

/* 🔹 Toolbar */
.notifications__toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding: 16px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.toolbar__filters {
    display: flex;
    gap: 12px;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    color: #4a5568;
    cursor: pointer;
    background: white;
}

.toolbar__actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.search-input {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    width: 200px;
}

.mark-all-btn {
    background: none;
    border: none;
    color: #4299e1;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
}

.mark-all-btn:hover {
    color: #2b6cb0;
}

/* 🔹 Notification Card */
.notification-card {
    display: flex;
    gap: 16px;
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 12px;
    transition: all 0.2s;
    cursor: pointer;
}

.notification-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border-color: #cbd5e0;
}

.notification-card.is-unread {
    background: #fff;
    border-left: 4px solid #4299e1;
}

.notification-card.is-unread:hover {
    background: #f7fafc;
}

/* Indicator */
.notification__indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 4px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4299e1;
    margin-bottom: 12px;
}

.status-dot.is-read {
    background: transparent;
    /* Скрыт если прочитано */
}

.notification__icon {
    font-size: 24px;
}

/* Content */
.notification__content {
    flex: 1;
}

.notification__header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}

.notification__title {
    font-size: 16px;
    font-weight: 700;
    color: #1a202c;
    margin: 0;
}

.notification__time {
    font-size: 12px;
    color: #a0aec0;
    white-space: nowrap;
}

.notification__message {
    font-size: 14px;
    color: #4a5568;
    line-height: 1.5;
    margin: 0 0 16px;
}

.notification__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.course-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #edf2f7;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #4a5568;
}

.notification__actions {
    display: flex;
    gap: 12px;
}

.action-btn {
    font-size: 13px;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
}

.action-btn.link {
    color: #4299e1;
    font-weight: 600;
}

.action-btn.link:hover {
    text-decoration: underline;
}

.action-btn.icon-btn {
    font-size: 16px;
    opacity: 0.5;
}

.action-btn.icon-btn:hover {
    opacity: 1;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #a0aec0;
}

.empty-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 16px;
}

/* Loading Skeleton */
.skeleton-card {
    height: 80px;
    background: #edf2f7;
    border-radius: 12px;
    margin-bottom: 12px;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        opacity: 0.6;
    }

    50% {
        opacity: 1;
    }

    100% {
        opacity: 0.6;
    }
}
</style>