<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { useAuth } from '../composables/useAuth'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const { currentUser } = useAuth()
const { pushStatus } = useToast()

const courseData = ref(null)
const isLoading = ref(true)

const getLevelText = (level) => {
    const levels = {
        beginner: 'Начальный',
        intermediate: 'Средний',
        advanced: 'Продвинутый'
    }
    return levels[level] || 'Начальный'
}

const getWhatYouLearnList = () => {
    if (!courseData.value?.course?.what_you_learn) {
        return [
            'Применять главные принципы дизайна',
            'Узнаете всё о цвете',
            'Выбирать и сочетать шрифты',
            'Разрабатывать концепцию и логотип',
            'Создавать цепляющий визуал'
        ]
    }
    return courseData.value.course.what_you_learn.split('\n').filter(line => line.trim())
}

const formatText = (text) => {
    if (!text) return ''
    return text
        .split('\n\n')
        .map(paragraph => `<p>${paragraph.replace(/\n/g, '<br>')}</p>`)
        .join('')
}

const isFree = computed(() => {
    const price = parseFloat(courseData.value?.course?.price ?? 0)
    return price === 0 || courseData.value?.course?.is_free === true
})

async function loadCourse() {
    isLoading.value = true
    try {
        const response = await api.getCourse(route.params.id)
        courseData.value = response

        if (currentUser.value) {
            await checkWishlistStatus()
        }

    } catch (err) {
        console.error('Failed to load course:', err)
        pushStatus('Не удалось загрузить курс', 'error')
        // Fallback data
        courseData.value = {
            course: {
                id: route.params.id,
                title: 'Курс',
                description: 'Описание курса',
                level: 'beginner',
                duration_hours: 3,
                lessons_count: 20,
                video_duration: '3 часа',
                tests_count: 5,
                rating: 5.0,
                students_count: 100,
                what_you_learn: '',
                about_course: '',
                is_free: true,
                price: 0
            },
            modules: {}
        }
    } finally {
        isLoading.value = false
    }
}

function handleCourseAction() {
    if (!currentUser.value) {
        pushStatus('Войдите, чтобы записаться на курс', 'info')
        router.push('/?auth=login')
        return
    }

    const course = courseData.value?.course

    // 🔹 Если курс бесплатный — сразу запускаем обучение
    if (isFree.value) {
        pushStatus('Запись на курс...', 'info')
        api.enroll(course.id)
            .then(() => {
                pushStatus(`Вы записались на "${course.title}"!`, 'success')
                startLearning()
            })
            .catch(err => pushStatus(err.message || 'Ошибка записи', 'error'))
    }
    // 🔹 Если платный — показываем информацию об оплате
    else {
        pushStatus(`Переход к оплате: ${course.price} ₽`, 'info', 5000)
        // Здесь можно добавить редирект на страницу оплаты
        // router.push(`/payment/${course.id}`)
    }
}

function startLearning() {
    router.push(`/learning/${courseData.value.course.id}`)
}


const isInWishlist = ref(false)

async function toggleWishlist() {
    if (!currentUser.value) {
        pushStatus('Войдите, чтобы добавить в избранное', 'info')
        router.push('/?auth=login')
        return
    }
    try {
        const res = await api.toggleWishlist(courseData.value.course.id)
        isInWishlist.value = res.in_wishlist
        pushStatus(res.in_wishlist ? 'Добавлено в «Хочу пройти» ❤️' : 'Удалено из избранного', 'success')
    } catch (err) {
        pushStatus(err.message || 'Ошибка', 'error')
    }
}

// Загрузите статус при открытии курса (добавьте в конец loadCourse или onMounted)
async function checkWishlistStatus() {
    if (!currentUser.value || !courseData.value?.course?.id) return

    try {
        const res = await api.getUserWishlist()
        // Ищем курс в списке
        const found = res.courses.some(c => c.id === courseData.value.course.id)
        isInWishlist.value = found
    } catch (err) {
        console.error('Failed to check wishlist:', err)
    }
}

onMounted(() => {
    loadCourse()
})
</script>

<template>
    <div class="course">
        <div v-if="isLoading" class="course__loading">
            <div class="course__loader"></div>
            <p>Загрузка курса...</p>
        </div>

        <div v-else-if="courseData" class="course__content">
            <!-- Header -->
            <section class="course__header">
                <div class="course__header-container">
                    <div class="course__header-main">
                        <h1 class="course__title">{{ courseData.course.title }}</h1>
                        <p class="course__description">{{ courseData.course.description }}</p>

                        <div class="course__meta">
                            <div class="course__meta-item">
                                <span class="course__meta-icon">📊</span>
                                <div class="course__meta-text">
                                    <strong>{{ getLevelText(courseData.course.level) }}</strong>
                                    <span>уровень</span>
                                </div>
                            </div>
                            <div class="course__meta-item">
                                <span class="course__meta-icon">⏱</span>
                                <div class="course__meta-text">
                                    <strong>{{ courseData.course.video_duration || '3-4 часа' }}</strong>
                                </div>
                            </div>
                            <div class="course__meta-item">
                                <span class="course__meta-icon">📜</span>
                                <div class="course__meta-text">
                                    <strong>Сертификат</strong>
                                    <span>{{ courseData.course.certificate || 'TeacherPro' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="course__header-side">
                        <div class="course__image-placeholder"></div>
                        <div class="course__stats-right">
                            <div class="course__rating-block">
                                <div class="course__stars">★★★★★</div>
                                <span class="course__rating-value">{{ courseData.course.rating || '5.0' }}</span>
                            </div>
                            <span class="course__students">{{ courseData.course.students_count || 100 }} учащихся</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Main Content -->
            <div class="course__body">
                <div class="course__main">
                    <!-- What you'll learn -->
                    <section class="course__section">
                        <h2 class="course__section-title">Чему вы научитесь</h2>
                        <div class="course__learn-list">
                            <div v-for="(item, index) in getWhatYouLearnList()" :key="index" class="course__learn-item">
                                <span class="course__check">✓</span>
                                <span>{{ item }}</span>
                            </div>
                        </div>
                    </section>

                    <!-- About -->
                    <section class="course__section">
                        <h2 class="course__section-title">О курсе</h2>
                        <div class="course__about"
                            v-html="formatText(courseData.course.about_course || courseData.course.description)"></div>
                    </section>

                    <section class="course__section">
                        <h2 class="course__section-title">Программа курса</h2>

                        <div v-if="!courseData.modules || courseData.modules.length === 0"
                            class="course__empty-modules">
                            <p>Программа курса скоро появится</p>
                        </div>

                        <div v-else class="course__modules">
                            <!-- 🔹 Итерируем как массив объектов -->
                            <div v-for="module in courseData.modules" :key="module.id" class="course__module">
                                <div class="course__module-header">
                                    <span class="course__module-title">{{ module.title }}</span>
                                    <span class="course__module-count">{{ module.lessons ? module.lessons.length : 0 }}
                                        уроков</span>
                                </div>

                                <!-- Если уроки есть -->
                                <div v-if="module.lessons && module.lessons.length > 0" class="course__module-lessons">
                                    <div v-for="lesson in module.lessons" :key="lesson.id" class="course__lesson-item">
                                        <span class="course__lesson-icon">▶</span>
                                        <span class="course__lesson-title">{{ lesson.title }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Sidebar -->
                <aside class="course__sidebar">
                    <div class="course__price-card">
                        <div class="course__price" :class="{ 'course__price--free': isFree }">
                            {{ isFree ? 'Бесплатно' : `${courseData.course.price} ₽` }}
                        </div>

                        <button class="course__btn course__btn--primary" :class="{ 'course__btn--free': isFree }"
                            @click="handleCourseAction">
                            {{ isFree ? 'Пройти курс' : 'Купить' }}
                        </button>

                        <button class="course__btn course__btn--wishlist"
                            :class="{ 'course__btn--wishlist--active': isInWishlist }" @click="toggleWishlist">
                            {{ isInWishlist ? '♥ В списке' : '♡ Хочу пройти' }}
                        </button>

                        <div class="course__info-box">
                            <h4>В курс входят</h4>
                            <ul>
                                <li>{{ courseData.course.lessons_count || 32 }} урока</li>
                                <li>{{ courseData.course.video_duration || '3 часа' }} видео</li>
                                <li>{{ courseData.course.tests_count || 10 }} тестов</li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>

            <button class="course__back" @click="router.back()">← Вернуться</button>
        </div>
    </div>
</template>

<style scoped>
.course {
    min-height: 100vh;
    background: #f7fafc;
}

.course__loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    gap: 20px;
}

.course__loader {
    width: 48px;
    height: 48px;
    border: 4px solid #e2e8f0;
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Header */
.course__header {
    background: #000000;
    color: white;
    padding: 60px 0 40px;
}

.course__header-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 48px;
    align-items: start;
}

.course__title {
    font-size: 40px;
    font-weight: 800;
    margin: 0 0 16px;
    line-height: 1.2;
}

.course__description {
    font-size: 18px;
    line-height: 1.6;
    color: #e2e8f0;
    margin: 0 0 32px;
}

.course__meta {
    display: flex;
    gap: 32px;
    flex-wrap: wrap;
}

.course__meta-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.course__meta-icon {
    font-size: 24px;
    opacity: 0.9;
}

.course__meta-text {
    display: flex;
    flex-direction: column;
}

.course__meta-text strong {
    font-size: 15px;
    font-weight: 600;
}

.course__meta-text span {
    font-size: 13px;
    color: #a0aec0;
}

.course__header-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.course__image-placeholder {
    width: 240px;
    height: 240px;
    border-radius: 16px;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    margin-bottom: 20px;
    border: 1px solid #333333;
}

.course__stats-right {
    text-align: right;
}

.course__rating-block {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    justify-content: flex-end;
}

.course__stars {
    color: #fbbf24;
    font-size: 18px;
}

.course__rating-value {
    font-size: 24px;
    font-weight: 800;
}

.course__students {
    color: #a0aec0;
    font-size: 14px;
}

/* Body */
.course__body {
    max-width: 1280px;
    margin: 0 auto;
    padding: 40px 24px;
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 48px;
}

.course__main {
    background: white;
    border-radius: 16px;
    padding: 40px;
    border: 1px solid #e2e8f0;
}

.course__section {
    margin-bottom: 48px;
}

.course__section:last-child {
    margin-bottom: 0;
}

.course__section-title {
    font-size: 28px;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #000000;
}

.course__price--free {
    color: #48bb78 !important;
}

.course__btn--free {
    background: #48bb78 !important;
}

.course__btn--free:hover {
    background: #38a169 !important;
}

/* Learn list */
.course__learn-list {
    display: grid;
    gap: 14px;
}

.course__learn-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    font-size: 16px;
    line-height: 1.5;
    color: #2d3748;
}

.course__check {
    color: #48bb78;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}

/* About */
.course__about {
    font-size: 16px;
    line-height: 1.7;
    color: #2d3748;
}

.course__about :deep(p) {
    margin-bottom: 16px;
}

/* Modules */
.course__modules {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.course__module {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.course__module-header {
    background: #f7fafc;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}

.course__module-title {
    font-weight: 700;
    color: #1a202c;
}

.course__module-count {
    color: #718096;
    font-size: 14px;
}

.course__module-lessons {
    padding: 8px 0;
}

.course__lesson-item {
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: background 0.2s;
}

.course__lesson-item:hover {
    background: #f7fafc;
}

.course__lesson-icon {
    color: #000000;
    font-size: 12px;
}

.course__lesson-title {
    color: #2d3748;
    font-size: 15px;
}

/* Sidebar */
.course__sidebar {
    position: sticky;
    top: 100px;
    height: fit-content;
}

.course__price-card {
    background: white;
    border-radius: 16px;
    padding: 32px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.course__price {
    font-size: 36px;
    font-weight: 800;
    color: #000000;
    margin-bottom: 24px;
}

.course__price--free {
    color: #48bb78;
}

.course__btn {
    width: 100%;
    padding: 14px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    margin-bottom: 12px;
}

.course__btn--primary {
    background: #000000;
    color: white;
}

.course__btn--primary:hover {
    background: #2d2d2d;
    transform: translateY(-2px);
}

.course__btn--ghost {
    background: white;
    color: #000000;
    border: 2px solid #000000;
}

.course__btn--ghost:hover {
    background: #f7fafc;
}

.course__info-box {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
}

.course__info-box h4 {
    margin: 0 0 16px;
    font-size: 15px;
    font-weight: 700;
    color: #1a202c;
}

.course__info-box ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.course__info-box li {
    font-size: 14px;
    color: #4a5568;
    margin-bottom: 10px;
    padding-left: 20px;
    position: relative;
}

.course__info-box li::before {
    content: '•';
    position: absolute;
    left: 0;
    color: #000000;
    font-weight: bold;
}

.course__back {
    position: fixed;
    bottom: 30px;
    left: 30px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    color: #000000;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
}

.course__back:hover {
    background: #f7fafc;
    transform: translateY(-2px);
}

@media (max-width: 968px) {

    .course__header-container,
    .course__body {
        grid-template-columns: 1fr;
    }

    .course__sidebar {
        position: static;
    }

    .course__header-side {
        align-items: flex-start;
    }

    .course__image-placeholder {
        width: 100%;
        max-width: 320px;
    }

    .course__main {
        padding: 24px;
    }

    .course__title {
        font-size: 32px;
    }
}

.course__btn--wishlist {
    width: 100%;
    padding: 12px;
    background: #fff;
    color: #000;
    border: 2px solid #000;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 12px;
}

.course__btn--wishlist:hover {
    background: #b3b5b5;
}

.course__btn--wishlist--active {
    background: #000;
    color: #fff;
    border-color: #000;
}
</style>