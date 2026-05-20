<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { useAuth } from '../composables/useAuth'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { currentUser } = useAuth()
const { pushStatus } = useToast()

// 🔹 Состояние вкладок и данных
const activeTab = ref('in-progress')
const isLoading = ref(false)

// 🔹 ОБЯЗАТЕЛЬНО: объявляем массивы на верхнем уровне
const enrolledCourses = ref([])
const recommendedCourses = ref([])
const wishlistCourses = ref([]) // ✅ FIX: теперь Vue видит эту переменную

// Переключение вкладок
function switchTab(tab) {
    activeTab.value = tab
}

// Загрузка основных данных (прохожу + рекомендации)
async function loadData() {
    if (!currentUser.value) return
    isLoading.value = true
    try {
        const enrolledRes = await api.getUserEnrollments()
        enrolledCourses.value = enrolledRes.courses || []

        const allCoursesRes = await api.getCourses()
        const allCourses = allCoursesRes.courses || []

        const enrolledIds = new Set(enrolledCourses.value.map(c => c.id))
        recommendedCourses.value = allCourses
            .filter(course => !enrolledIds.has(course.id))
            .slice(0, 6)
    } catch (err) {
        console.error('Failed to load data:', err)
        pushStatus('Не удалось загрузить данные', 'error')
    } finally {
        isLoading.value = false
    }
}

// Загрузка списка "Хочу пройти"
async function loadWishlist() {
    if (!currentUser.value) return
    try {
        const res = await api.getUserWishlist()
        wishlistCourses.value = res.courses || [] // ✅ Безопасное присваивание
    } catch (err) {
        console.error('Failed to load wishlist:', err)
    }
}

// Действия с курсами
function continueCourse(courseId) {
    router.push(`/learning/${courseId}`)
}

function openCourse(courseId) {
    router.push(`/course/${courseId}`)
}

function startLearning(course) {
    if (!currentUser.value) {
        pushStatus('Войдите, чтобы начать обучение', 'info')
        router.push('/?auth=login')
        return
    }
    pushStatus('Запись на курс...', 'info')
    api.enroll(course.id)
        .then(() => {
            pushStatus(`Вы записались на "${course.title}"!`, 'success')
            router.push(`/learning/${course.id}`)
        })
        .catch(err => pushStatus(err.message || 'Ошибка записи', 'error'))
}

function handleBuy(course) {
    if (!currentUser.value) {
        pushStatus('Войдите, чтобы купить курс', 'info')
        router.push('/?auth=login')
        return
    }
    pushStatus(`Переход к оплате: ${course.price} ₽`, 'info', 5000)
}

function toggleWishlist(courseId) {
    api.toggleWishlist(courseId)
        .then(() => pushStatus('Добавлено в избранное', 'success'))
        .catch(err => pushStatus(err.message || 'Ошибка', 'error'))
}

function removeFromWishlist(courseId) {
    if (!confirm('Удалить курс из списка?')) return
    api.toggleWishlist(courseId)
        .then(() => {
            wishlistCourses.value = wishlistCourses.value.filter(c => c.id !== courseId)
            pushStatus('Курс удалён из списка', 'success')
        })
        .catch(err => pushStatus(err.message || 'Ошибка', 'error'))
}

// Инициализация при монтировании
onMounted(() => {
    loadData()
    loadWishlist()
})
</script>

<template>
    <div class="my-learning">
        <!-- Sidebar -->
        <aside class="my-learning__sidebar">
            <div class="sidebar__banner">
                <div class="sidebar__banner-content">
                    <span class="sidebar__banner-icon">📚</span>
                    <span class="sidebar__banner-text">Учитесь с удовольствием</span>
                </div>
            </div>

            <nav class="sidebar__nav">
                <!-- Моё обучение (переключает на первую вкладку) -->
                <button class="sidebar__link sidebar__link--active" @click="switchTab('in-progress')">
                    <span class="sidebar__icon">🏠</span> Моё обучение
                </button>

                <div class="sidebar__submenu">
                    <div class="sidebar__submenu-header">
                        <span class="sidebar__icon">📖</span> Курсы <span class="sidebar__arrow">▼</span>
                    </div>
                    <div class="sidebar__submenu-items">
                        <button class="sidebar__submenu-link"
                            :class="{ 'sidebar__submenu-link--active': activeTab === 'in-progress' }"
                            @click="switchTab('in-progress')">Прохожу</button>
                        <button class="sidebar__submenu-link"
                            :class="{ 'sidebar__submenu-link--active': activeTab === 'wishlist' }"
                            @click="switchTab('wishlist')">Хочу пройти</button>
                        <button class="sidebar__submenu-link"
                            :class="{ 'sidebar__submenu-link--active': activeTab === 'recommended' }"
                            @click="switchTab('recommended')">Рекомендованные</button>
                    </div>
                </div>

            </nav>

            <a href="#" class="sidebar__help">
                <span class="sidebar__help-icon">❓</span>
                Помощь
            </a>
        </aside>

        <!-- Main Content -->
        <main class="my-learning__main">
            <h1 class="my-learning__title">Моё обучение</h1>

            <!-- Вкладки -->
            <div class="my-learning__tabs">
                <button :class="['my-learning__tab', { active: activeTab === 'in-progress' }]"
                    @click="switchTab('in-progress')">
                    Прохожу
                    <span v-if="enrolledCourses.length > 0" class="my-learning__tab-count">
                        {{ enrolledCourses.length }}
                    </span>
                </button>

                <button :class="['my-learning__tab', { active: activeTab === 'wishlist' }]"
                    @click="switchTab('wishlist')">
                    Хочу пройти
                    <span v-if="wishlistCourses.length > 0" class="my-learning__tab-count">
                        {{ wishlistCourses.length }}
                    </span>
                </button>

                <button :class="['my-learning__tab', { active: activeTab === 'recommended' }]"
                    @click="switchTab('recommended')">
                    Рекомендованные
                </button>
            </div>

            <div v-if="isLoading" class="my-learning__loading">
                <div class="my-learning__loader"></div>
                <span>Загрузка...</span>
            </div>

            <template v-else>
                <!-- Вкладка: Прохожу -->
                <div v-if="activeTab === 'in-progress'">
                    <section v-if="enrolledCourses.length > 0" class="my-learning__section">
                        <div class="courses-list">
                            <div v-for="course in enrolledCourses" :key="course.id" class="courses-list__item">
                                <div class="courses-list__icon">
                                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" />
                                    <div v-else class="courses-list__placeholder">📚</div>
                                </div>

                                <div class="courses-list__info">
                                    <h3 class="courses-list__title">{{ course.title }}</h3>
                                    <p class="courses-list__author">{{ course.teacher_name || course.author }}</p>
                                    <div class="courses-list__progress">
                                        <span class="courses-list__progress-text">
                                            {{ course.progress || 0 }}/{{ course.total_lessons || 10 }}
                                        </span>
                                        <div class="courses-list__progress-bar">
                                            <div class="courses-list__progress-fill"
                                                :style="{ width: `${(course.progress || 0) / (course.total_lessons || 10) * 100}%` }">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button class="courses-list__btn" @click="continueCourse(course.id)">
                                    Продолжить →
                                </button>
                            </div>
                        </div>
                    </section>

                    <div v-else class="my-learning__empty">
                        <div class="my-learning__empty-icon">📚</div>
                        <h2>Вы ещё не записаны на курсы</h2>
                        <p>Начните обучение прямо сейчас — выберите курс из каталога</p>
                        <button class="my-learning__empty-btn" @click="router.push('/catalog')">
                            Перейти в каталог
                        </button>
                    </div>
                </div>

                <!-- Вкладка: Хочу пройти -->
                <div v-if="activeTab === 'wishlist'">
                    <section v-if="wishlistCourses.length > 0" class="my-learning__section">
                        <div class="wishlist-grid">
                            <div v-for="course in wishlistCourses" :key="course.id" class="wishlist-card">
                                <button class="wishlist-card__remove" @click="removeFromWishlist(course.id)"
                                    title="Удалить из списка">
                                    ✕
                                </button>

                                <div class="wishlist-card__thumbnail" @click="openCourse(course.id)">
                                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" />
                                    <div v-else class="wishlist-card__placeholder">📚</div>
                                </div>

                                <div class="wishlist-card__content">
                                    <h3 class="wishlist-card__title" @click="openCourse(course.id)">
                                        {{ course.title }}
                                    </h3>
                                    <p class="wishlist-card__author">{{ course.teacher_name || course.author }}</p>
                                    <p class="wishlist-card__description">{{ course.description }}</p>

                                    <div class="wishlist-card__meta">
                                        <span class="wishlist-card__rating">★ {{ course.rating || '5.0' }}</span>
                                        <span class="wishlist-card__students">👤 {{ course.students_count || 0 }}</span>
                                    </div>

                                    <div class="wishlist-card__actions">
                                        <button v-if="course.price === 0 || course.price === '0.00'"
                                            class="wishlist-card__btn wishlist-card__btn--primary"
                                            @click="startLearning(course)">
                                            Пройти
                                        </button>
                                        <button v-else class="wishlist-card__btn wishlist-card__btn--primary"
                                            @click="handleBuy(course)">
                                            {{ course.price }} ₽
                                        </button>
                                        <button class="wishlist-card__btn" @click="openCourse(course.id)">
                                            Подробнее
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div v-else class="my-learning__empty">
                        <div class="my-learning__empty-icon">💚</div>
                        <h2>Список желаний пуст</h2>
                        <p>Добавляйте курсы, которые хотите пройти, нажимая на кнопку "Хочу пройти"</p>
                        <button class="my-learning__empty-btn" @click="router.push('/catalog')">
                            Перейти в каталог
                        </button>
                    </div>
                </div>

                <!-- Вкладка: Рекомендованные -->
                <div v-if="activeTab === 'recommended'">
                    <section v-if="recommendedCourses.length > 0" class="my-learning__section">
                        <p class="my-learning__section-subtitle">
                            Подобрали на основе вашей активности. Учитесь — и рекомендации станут точнее.
                        </p>

                        <div class="recommended-grid">
                            <div v-for="course in recommendedCourses" :key="course.id" class="recommended-card"
                                @click="openCourse(course.id)">
                                <div class="recommended-card__header">
                                    <div class="recommended-card__thumbnail">
                                        <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" />
                                        <div v-else class="recommended-card__placeholder">📚</div>
                                    </div>
                                    <span v-if="course.price === 0 || course.price === '0.00'"
                                        class="recommended-card__free">
                                        Бесплатно
                                    </span>
                                    <button class="recommended-card__wishlist" @click.stop="toggleWishlist(course.id)">
                                        ♡
                                    </button>
                                </div>

                                <div class="recommended-card__content">
                                    <h3 class="recommended-card__title">{{ course.title }}</h3>
                                    <p class="recommended-card__author">{{ course.teacher_name || course.author }}</p>
                                    <p class="recommended-card__description">{{ course.description }}</p>

                                    <div class="recommended-card__meta">
                                        <span class="recommended-card__rating">★ {{ course.rating || '5.0' }}</span>
                                        <span class="recommended-card__students">👤 {{ course.students_count || 0
                                        }}</span>
                                        <span class="recommended-card__duration">⏱ {{ course.duration || '4ч' }}</span>
                                    </div>

                                    <div class="recommended-card__footer">
                                        <button class="recommended-card__btn" @click.stop="openCourse(course.id)">
                                            Подробнее
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </template>
        </main>
    </div>
</template>

<style scoped>
.my-learning {
    display: grid;
    grid-template-columns: 280px 1fr;
    min-height: calc(100vh - 64px);
    background: #ffffff;
}

/* Sidebar */
.my-learning__sidebar {
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    padding: 24px 0;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 64px;
    height: calc(100vh - 64px);
    overflow-y: auto;
}

.sidebar__banner {
    margin: 0 16px 24px;
    padding: 20px;
    background: #000000;
    border-radius: 12px;
    color: #ffffff;
}

.sidebar__banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar__banner-icon {
    font-size: 32px;
}

.sidebar__banner-text {
    font-weight: 600;
    font-size: 14px;
}

.sidebar__nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sidebar__link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    color: #4a5568;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.2s;
    position: relative;
}

.sidebar__link:hover {
    background: #f7fafc;
    color: #000000;
}

.sidebar__link--active {
    color: #000000;
    font-weight: 600;
    background: #f7fafc;
}

.sidebar__link--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #000000;
}

.sidebar__icon {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.sidebar__badge {
    margin-left: auto;
    background: #000000;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
}

/* Submenu */
.sidebar__submenu {
    margin: 8px 0;
}

.sidebar__submenu-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    color: #4a5568;
    font-size: 15px;
    cursor: pointer;
}

.sidebar__submenu-header:hover {
    background: #f7fafc;
}

.sidebar__arrow {
    margin-left: auto;
    font-size: 10px;
    color: #a0aec0;
}

.sidebar__submenu-items {
    padding-left: 48px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sidebar__submenu-link {
    padding: 8px 16px;
    color: #718096;
    text-decoration: none;
    font-size: 14px;
    border-radius: 6px;
    transition: all 0.2s;
}

.sidebar__submenu-link:hover {
    background: #f7fafc;
    color: #000000;
}

.sidebar__submenu-link--active {
    color: #000000;
    font-weight: 600;
    background: #edf2f7;
}

.sidebar__help {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    margin-top: auto;
    color: #718096;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.2s;
}

.sidebar__help:hover {
    background: #f7fafc;
    color: #000000;
}

.sidebar__help-icon {
    font-size: 18px;
}

/* Main Content */
.my-learning__main {
    padding: 40px;
    background: #ffffff;
}

.my-learning__title {
    font-size: 32px;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 40px;
}

.my-learning__loading {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 60px;
    color: #718096;
}

.my-learning__loader {
    width: 24px;
    height: 24px;
    border: 3px solid #e2e8f0;
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Sections */
.my-learning__section {
    margin-bottom: 48px;
}

.my-learning__section-title {
    font-size: 24px;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 24px;
}

.my-learning__section-subtitle {
    color: #718096;
    font-size: 15px;
    margin: -16px 0 24px;
}

/* 🔹 Сброс стандартных стилей кнопок */
button.sidebar__link,
button.sidebar__submenu-link {
  background: none;
  border: none;
  padding: 0;
  margin: 0;
  font-family: inherit;
  font-size: inherit;
  font-weight: inherit;
  color: inherit;
  cursor: pointer;
  text-align: left;
  width: 100%;
  display: flex;
  align-items: center;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
}

/* Возвращаем корректные отступы и промежутки */
button.sidebar__link {
  padding: 12px 24px;
  gap: 12px;
}

button.sidebar__submenu-link {
  padding: 8px 16px;
  justify-content: flex-start;
}

button.my-learning__tab {
  width: auto;
  padding: 12px 24px;
  justify-content: center;
  gap: 8px;
  margin-bottom: -2px; /* Для стыковки с нижней границей вкладок */
}

/* Фикс для активного состояния (чёрная полоска слева) */
button.sidebar__link--active {
  position: relative;
}

/* Courses List */
.courses-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.courses-list__item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
    cursor: pointer;
}

.courses-list__item:hover {
    border-color: #000000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.courses-list__icon {
    width: 64px;
    height: 64px;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
}

.courses-list__icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.courses-list__placeholder {
    font-size: 32px;
    opacity: 0.5;
}

.courses-list__info {
    flex: 1;
}

.courses-list__title {
    font-size: 16px;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 4px;
}

.courses-list__author {
    font-size: 14px;
    color: #718096;
    margin: 0 0 12px;
}

.courses-list__progress {
    display: flex;
    align-items: center;
    gap: 12px;
}

.courses-list__progress-text {
    font-size: 13px;
    color: #4a5568;
    font-weight: 600;
    min-width: 60px;
}

.courses-list__progress-bar {
    flex: 1;
    max-width: 200px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
}

.courses-list__progress-fill {
    height: 100%;
    background: #000000;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.courses-list__btn {
    padding: 10px 24px;
    background: #ffffff;
    color: #000000;
    border: 2px solid #000000;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.courses-list__btn:hover {
    background: #000000;
    color: #ffffff;
}

/* Recommended Grid */
.recommended-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.recommended-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s;
}

.recommended-card:hover {
    border-color: #000000;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
}

.recommended-card__header {
    position: relative;
}

.recommended-card__thumbnail {
    height: 160px;
    background: #f7fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #e2e8f0;
}

.recommended-card__thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.recommended-card__placeholder {
    font-size: 64px;
    opacity: 0.2;
}

.recommended-card__free {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #000000;
    color: #ffffff;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.recommended-card__wishlist {
    position: absolute;
    top: 12px;
    left: 12px;
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.recommended-card__wishlist:hover {
    background: #000000;
    color: #ffffff;
    border-color: #000000;
    transform: scale(1.1);
}

.recommended-card__content {
    padding: 20px;
}

.recommended-card__title {
    font-size: 16px;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 8px;
    line-height: 1.3;
}

.recommended-card__author {
    font-size: 14px;
    color: #718096;
    margin: 0 0 12px;
}

.recommended-card__description {
    font-size: 14px;
    color: #4a5568;
    line-height: 1.5;
    margin: 0 0 16px;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.recommended-card__meta {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #718096;
}

.recommended-card__rating,
.recommended-card__students,
.recommended-card__duration {
    display: flex;
    align-items: center;
    gap: 4px;
}

.recommended-card__footer {
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.recommended-card__btn {
    width: 100%;
    padding: 10px;
    background: #ffffff;
    color: #000000;
    border: 2px solid #000000;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.recommended-card__btn:hover {
    background: #000000;
    color: #ffffff;
}

/* Вкладки */
.my-learning__tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 32px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0;
}

.my-learning__tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: #718096;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -2px;
}

.my-learning__tab:hover {
    color: #000000;
}

.my-learning__tab.active {
    color: #000000;
    border-bottom-color: #000000;
}

.my-learning__tab-count {
    background: #e2e8f0;
    color: #4a5568;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
}

.my-learning__tab.active .my-learning__tab-count {
    background: #000000;
    color: #ffffff;
}

/* Wishlist Grid */
.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.wishlist-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
}

.wishlist-card:hover {
    border-color: #000000;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
}

.wishlist-card__remove {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    color: #718096;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    z-index: 10;
    opacity: 0;
}

.wishlist-card:hover .wishlist-card__remove {
    opacity: 1;
}

.wishlist-card__remove:hover {
    background: #e53e3e;
    color: #ffffff;
    border-color: #e53e3e;
    transform: scale(1.1);
}

.wishlist-card__thumbnail {
    height: 160px;
    background: #f7fafc;
    cursor: pointer;
    overflow: hidden;
}

.wishlist-card__thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.wishlist-card:hover .wishlist-card__thumbnail img {
    transform: scale(1.05);
}

.wishlist-card__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 64px;
    opacity: 0.2;
}

.wishlist-card__content {
    padding: 20px;
}

.wishlist-card__title {
    font-size: 16px;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 8px;
    line-height: 1.3;
    cursor: pointer;
    transition: color 0.2s;
}

.wishlist-card__title:hover {
    color: #000000;
}

.wishlist-card__author {
    font-size: 14px;
    color: #718096;
    margin: 0 0 12px;
}

.wishlist-card__description {
    font-size: 14px;
    color: #4a5568;
    line-height: 1.5;
    margin: 0 0 16px;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.wishlist-card__meta {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #718096;
}

.wishlist-card__rating,
.wishlist-card__students {
    display: flex;
    align-items: center;
    gap: 4px;
}

.wishlist-card__actions {
    display: flex;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.wishlist-card__btn {
    flex: 1;
    padding: 10px;
    background: #ffffff;
    color: #000000;
    border: 2px solid #000000;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.wishlist-card__btn:hover {
    background: #000000;
    color: #ffffff;
}

.wishlist-card__btn--primary {
    background: #000000;
    color: #ffffff;
}

.wishlist-card__btn--primary:hover {
    background: #2d2d2d;
}

/* Empty State */
.my-learning__empty {
    text-align: center;
    padding: 80px 20px;
}

.my-learning__empty-icon {
    font-size: 80px;
    margin-bottom: 24px;
    opacity: 0.2;
}

.my-learning__empty h2 {
    font-size: 24px;
    color: #1a202c;
    margin: 0 0 12px;
}

.my-learning__empty p {
    color: #718096;
    margin: 0 0 32px;
    font-size: 16px;
}

.my-learning__empty-btn {
    padding: 14px 32px;
    background: #000000;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.my-learning__empty-btn:hover {
    background: #2d2d2d;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

/* Responsive */
@media (max-width: 968px) {
    .my-learning {
        grid-template-columns: 1fr;
    }

    .my-learning__sidebar {
        display: none;
    }

    .my-learning__main {
        padding: 24px;
    }

    .recommended-grid {
        grid-template-columns: 1fr;
    }
}
</style>