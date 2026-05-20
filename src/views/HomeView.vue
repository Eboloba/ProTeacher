<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import CourseCard from '../components/ui/CourseCard.vue'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { pushStatus } = useToast()

const dbCourses = ref([])

// Табы для каждой секции
const activeOnlineTab = ref('В тренде')
const activeProgramsTab = ref('Python')
const activeItTab = ref('Python')

const onlineTabs = ["В тренде", "Новые курсы", "Подготовка к ЕГЭ", "ИИ на каждый день"]
const programsTabs = ["Python", "Анализ данных", "QA и тестирование ПО", "Веб-разработка", "ЕГЭ и ОГЭ"]
const itTabs = ["Python", "DevOps", "ИИ в программировании", "Тестирование ПО", "Наука о данных", "SQL"]

// Ключевые слова для фильтрации по табам
const keywordsByTab = {
    // Онлайн-курсы
    "В тренде": ["python", "javascript", "sql", "веб", "frontend", "backend"],
    "Новые курсы": ["нов", "введение", "с нуля", "баз", "основы"],
    "Курсы с поддержкой": ["практик", "интенсив", "курс", "професс", "ментор"],
    "Подготовка к ЕГЭ": ["егэ", "огэ", "школ", "подготов"],
    "ИИ на каждый день": ["ai", "ии", "нейросет", "chatgpt", "llm", "искусственный интеллект"],

    // Программы курсов
    "Python": ["python"],
    "Анализ данных": ["данн", "аналит", "excel", "tableau", "power bi", "sql"],
    "QA и тестирование ПО": ["qa", "тест", "pytest", "selenium", "playwright", "автотест"],
    "Веб-разработка": ["веб", "frontend", "backend", "javascript", "php", "html", "css", "vue", "react"],
    "ЕГЭ и ОГЭ": ["егэ", "огэ"],

    // Войти в IT
    "DevOps": ["devops", "docker", "kubernetes", "ci/cd", "linux", "terraform", "nginx"],
    "ИИ в программировании": ["ai", "ии", "нейросет", "chatgpt", "copilot"],
    "Тестирование ПО": ["тест", "qa", "pytest", "selenium", "playwright", "unit"],
    "Наука о данных": ["данн", "аналит", "ml", "machine", "sql", "статист", "pandas"],
    "SQL": ["sql", "mysql", "postgres", "база", "database"]
}

// Функция фильтрации курсов по ключевым словам таба
function filterCoursesByTab(tabName, maxCount = 6) {
    const words = keywordsByTab[tabName] || []

    if (!words.length || dbCourses.value.length === 0) {
        return dbCourses.value.slice(0, maxCount)
    }

    const matched = dbCourses.value.filter(course => {
        const title = (course.title || '').toLowerCase()
        const desc = (course.description || '').toLowerCase()
        const hay = `${title} ${desc}`
        return words.some(w => hay.includes(w))
    })

    // Если нет совпадений — показываем первые курсы
    const result = matched.length > 0 ? matched : dbCourses.value
    return result.slice(0, maxCount)
}

// Вычисляемые свойства для каждой секции
const onlineCourses = computed(() => filterCoursesByTab(activeOnlineTab.value))
const programsCourses = computed(() => filterCoursesByTab(activeProgramsTab.value))
const itCourses = computed(() => filterCoursesByTab(activeItTab.value))
const featuredCourses = computed(() => dbCourses.value.slice(0, 2))

// Авторы
const authors = ["Artiom Rusau", "Ринат Минязев", "Ляйсан Хутова", "Павел Тарасов", "Людмила Колесникова", "Илья Перминов"]

// Популярный тег
const popularTags = [
    "Python", "DevOps", "SQL", "Подготовка к ЕГЭ", "JavaScript", "Java",
    "Тестирование ПО", "Linux", "Аналитика данных", "Go", "Excel", "Vue.js",
]

// Маппинг курсов (приводим к единому формату)
function mapCourse(raw, index) {
    const rawPrice = parseFloat(raw.price ?? raw.cost ?? 0)
    const isFree = rawPrice === 0 || raw.is_free === true
    const displayPrice = isFree ? 'Бесплатно' : `${rawPrice} ₽`
    const buttonText = isFree ? 'Пройти' : 'Купить'

    return {
        id: raw.id ?? index,
        title: raw.title || "Без названия",
        author: raw.teacher_name || raw.author || "Автор курса",
        description: raw.description || "",
        price: rawPrice,
        displayPrice,
        is_free: isFree,
        button_text: buttonText,
        rating: raw.rating ?? 5,
        students: raw.students ?? (100 + index * 9),
        duration: raw.duration ? `${raw.duration}ч` : `${4 + index}ч`
    }
}

// Преобразуем курсы при загрузке
const mappedCourses = computed(() => dbCourses.value.map(mapCourse))

async function loadCourses() {
    try {
        const res = await api.getCourses()
        dbCourses.value = Array.isArray(res.courses) ? res.courses : []
    } catch (err) {
        console.error('Failed to load courses:', err)
        pushStatus('Не удалось загрузить курсы', 'error')
    }
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
    .catch(err => {
      pushStatus(err.message || 'Ошибка записи', 'error')
    })
}

// 🔹 Для платных курсов — показываем инфо о покупке
function handleBuy(course) {
  if (!currentUser.value) {
    pushStatus('Войдите, чтобы купить курс', 'info')
    router.push('/?auth=login')
    return
  }
  
  pushStatus(`Переход к оплате: ${course.displayPrice}`, 'info', 5000)
  // Здесь можно добавить редирект на страницу оплаты:
  // router.push(`/payment/${course.id}`)
}

function openCourse(id) {
    router.push(`/course/${id}`)
}

function handleCourseAction(course) {
    pushStatus(course.is_free ? 'Запись на курс...' : `Переход к оплате: ${course.displayPrice}`, 'info')
}

function openCatalogCategory(category) {
    router.push(`/catalog?category=${category}`)
}

onMounted(() => {
    loadCourses()
})
</script>

<template>
    <div class="home">
        <section class="hero">
            <div class="hero__bg">
                <img src="@/assets/code-bg.gif" alt="" class="hero__bg-gif" />
                <div class="hero__bg-overlay"></div>
            </div>

            <div class="hero__container hero__container--overlay">
                <div class="hero__content">
                    <h1 class="hero__title">
                        Обучайся онлайн с<br>
                        <span class="hero__highlight">TeacherPro</span>
                    </h1>
                    <p class="hero__subtitle">
                        Тысячи курсов от ведущих экспертов. Начните свой путь в IT уже сегодня.
                    </p>
                    <div class="hero__actions">
                        <button class="hero__btn hero__btn--primary" @click="router.push('/catalog')">
                            Смотреть курсы
                        </button>
                        <button class="hero__btn hero__btn--secondary" @click="router.push('/catalog?free=true')">
                            Бесплатные курсы
                        </button>
                    </div>
                </div>
                
            </div>
        </section>

        <!-- Онлайн-курсы с фильтрацией -->
        <section class="section">
            <div class="section__container">
                <div class="section__header">
                    <h2 class="section__title">Онлайн-курсы</h2>
                    <button class="section__link" @click="router.push('/catalog')">Все курсы →</button>
                </div>

                <div class="tabs">
                    <button v-for="tab in onlineTabs" :key="tab"
                        :class="['tab', { 'tab--active': activeOnlineTab === tab }]" @click="activeOnlineTab = tab">
                        {{ tab }}
                    </button>
                </div>

                <div class="courses-grid">
                    <CourseCard v-for="course in mappedCourses.filter(c => {
                        const words = keywordsByTab[activeOnlineTab] || []
                        if (!words.length) return true
                        const hay = `${c.title} ${c.description}`.toLowerCase()
                        return words.some(w => hay.includes(w))
                    }).slice(0, 6)" :key="`online-${course.id}`" :course="course" @click="openCourse(course.id)"
                        @start="startLearning" @buy="handleBuy" />
                </div>
            </div>
        </section>

        <!-- Программы курсов с фильтрацией -->
        <section class="section">
            <div class="section__container">
                <div class="section__header">
                    <h2 class="section__title">Программы курсов</h2>
                    <button class="section__link" @click="router.push('/catalog')">Все программы →</button>
                </div>

                <div class="tabs">
                    <button v-for="tab in programsTabs" :key="tab"
                        :class="['tab', { 'tab--active': activeProgramsTab === tab }]" @click="activeProgramsTab = tab">
                        {{ tab }}
                    </button>
                </div>

                <div class="courses-grid">
                    <CourseCard v-for="course in mappedCourses.filter(c => {
                        const words = keywordsByTab[activeProgramsTab] || []
                        if (!words.length) return true
                        const hay = `${c.title} ${c.description}`.toLowerCase()
                        return words.some(w => hay.includes(w))
                    }).slice(0, 6)" :key="`programs-${course.id}`" :course="course" @click="openCourse(course.id)"
                        @start="startLearning" @buy="handleBuy" />
                </div>
            </div>
        </section>

        <!-- Войти в IT с фильтрацией -->
        <section class="section section--gray">
            <div class="section__container">
                <div class="section__header">
                    <h2 class="section__title">Войти в IT</h2>
                    <button class="section__link" @click="router.push('/catalog')">Все направления →</button>
                </div>

                <div class="tabs">
                    <button v-for="tab in itTabs" :key="tab" :class="['tab', { 'tab--active': activeItTab === tab }]"
                        @click="activeItTab = tab">
                        {{ tab }}
                    </button>
                </div>

                <div class="courses-grid">
                    <CourseCard v-for="course in mappedCourses.filter(c => {
                        const words = keywordsByTab[activeItTab] || []
                        if (!words.length) return true
                        const hay = `${c.title} ${c.description}`.toLowerCase()
                        return words.some(w => hay.includes(w))
                    }).slice(0, 6)" :key="`it-${course.id}`" :course="course" @click="openCourse(course.id)"
                        @start="startLearning" @buy="handleBuy" />
                </div>
            </div>
        </section>

        <!-- Categories -->
        <section class="section section--gray">
            <div class="section__container">
                <h2 class="section__title">Популярные направления</h2>

                <div class="categories-grid">
                    <div class="category-card" v-for="cat in popularTags" :key="cat"
                        @click="router.push(`/catalog?search=${cat}`)">
                        <div class="category-card__icon"></div>
                        <h3 class="category-card__title">{{ cat }}</h3>
                        <p class="category-card__desc">120+ курсов</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.home {
    min-height: 100vh;
}

/* Hero */
.hero {
    background: #000000;
    padding: 80px 0;
    color: white;
    position: relative;
    overflow: hidden;
}

.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.hero__container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    position: relative;
    z-index: 1;
}

.hero__title {
    font-size: 56px;
    font-weight: 800;
    line-height: 1.1;
    margin: 0 0 24px;
    letter-spacing: -1px;
}

.hero__highlight {
    color: #ffffff;
    border-bottom: 4px solid #ffffff;
}

.hero__subtitle {
    font-size: 20px;
    opacity: 0.8;
    margin: 0 0 32px;
    line-height: 1.6;
}

.hero__actions {
    display: flex;
    gap: 16px;
}

.hero__btn {
    padding: 14px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.hero__btn--primary {
    background: #ffffff;
    color: #000000;
}

.hero__btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 255, 255, 0.2);
}

.hero__btn--secondary {
    background: transparent;
    color: #ffffff;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.hero__btn--secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #ffffff;
}

.hero__placeholder {
    width: 100%;
    height: 400px;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    border-radius: 24px;
    border: 1px solid #333333;
}

/* === Popular Categories Section === */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
}

.category-card {
    background: white;
    padding: 32px 28px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
    overflow: hidden;
}

/* Градиентная полоска сверху при наведении */
.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #000000;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.category-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
    border-color: #000000;
}

.category-card:hover::before {
    transform: scaleX(1);
}

.category-card__icon {
    font-size: 48px;
    margin-bottom: 16px;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f7fafc;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.category-card:hover .category-card__icon {
    background: #000000;
    transform: scale(1.05);
}

/* Эффект при наведении: иконка становится белой на черном фоне */
.category-card:hover .category-card__icon::after {
    content: '';
    position: absolute;
    /* Можно добавить дополнительный эффект если нужно */
}

.category-card__title {
    font-size: 20px;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 8px;
    transition: color 0.2s ease;
}

.category-card:hover .category-card__title {
    color: #000000;
}

.category-card__desc {
    color: #718096;
    font-size: 14px;
    margin: 0;
    font-weight: 500;
}

/* === Адаптивность === */
@media (max-width: 968px) {
    .categories-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px;
    }

    .category-card {
        padding: 24px 20px;
    }

    .category-card__icon {
        width: 64px;
        height: 64px;
        font-size: 36px;
    }
}

@media (max-width: 640px) {
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .category-card {
        padding: 20px 16px;
    }

    .category-card__icon {
        width: 56px;
        height: 56px;
        font-size: 32px;
        margin-bottom: 12px;
    }

    .category-card__title {
        font-size: 16px;
    }

    .category-card__desc {
        font-size: 12px;
    }
}

/* Section */
.section {
    padding: 80px 0;
}

.section--gray {
    background: #f7fafc;
}

.section__container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
}

.section__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.section__title {
    font-size: 36px;
    font-weight: 800;
    color: #1a202c;
    margin: 0;
}

.section__link {
    background: none;
    border: none;
    color: #000000;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}

.section__link:hover {
    opacity: 0.7;
}

/* Tabs */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 32px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
}

.tabs::-webkit-scrollbar {
    display: none;
}

.tab {
    padding: 10px 20px;
    border: none;
    background: white;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    border: 1px solid #e2e8f0;
}

.tab:hover {
    border-color: #000000;
    color: #000000;
}

.tab--active {
    background: #000000;
    color: #ffffff;
    border-color: #000000;
}

/* Grid */
.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.courses-grid--mini {
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}

/* Author Section */
.author-section {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 32px;
    align-items: start;
}

.author-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.author-card__banner {
    height: 140px;
    background: linear-gradient(135deg, #f0f0f0 0%, #e0e0e0 100%);
}

.author-card__content {
    padding: 24px;
}

.author-card__name {
    font-size: 24px;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 12px;
}

.author-card__desc {
    color: #4a5568;
    font-size: 15px;
    line-height: 1.6;
    margin: 0 0 20px;
}

.author-card__btn {
    background: none;
    border: none;
    color: #000000;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}

.author-card__btn:hover {
    opacity: 0.7;
}

/* Authors Grid */
.authors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.author-item {
    background: white;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    gap: 16px;
    align-items: center;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}

.author-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.author-item__avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    flex-shrink: 0;
}

.author-item__info {
    flex: 1;
}

.author-item__name {
    font-size: 16px;
    font-weight: 600;
    color: #1a202c;
    margin: 0 0 4px;
}

.author-item__stats {
    color: #718096;
    font-size: 13px;
    margin: 0;
}

/* Tags */
.tags {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.tag {
    background: white;
    color: #2d3748;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 18px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.tag:hover {
    background: #000000;
    color: #ffffff;
    border-color: #000000;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Responsive */
@media (max-width: 968px) {
    .hero__container {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .hero__title {
        font-size: 40px;
    }

    .hero__actions {
        justify-content: center;
    }

    .hero__image {
        display: none;
    }

    .author-section {
        grid-template-columns: 1fr;
    }

    .section__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
}

@media (max-width: 640px) {
    .courses-grid {
        grid-template-columns: 1fr;
    }

    .hero__title {
        font-size: 32px;
    }

    .section__title {
        font-size: 28px;
    }
}

/* === Hero с GIF-фоном === */
.hero {
    position: relative;
    padding: 80px 0;
    color: white;
    overflow: hidden;
    min-height: 500px;
    display: flex;
    align-items: center;
}

/* Фоновый контейнер */
.hero__bg {
    position: absolute;
    inset: 0;
    z-index: 0;
}

.hero__bg-gif {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    opacity: 0.9;

}

/* Дополнительный градиент для лучшей читаемости */
.hero__bg-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,
            rgba(0, 0, 0, 0.7) 0%,
            rgba(0, 0, 0, 0.4) 50%,
            rgba(0, 0, 0, 0.6) 100%);
}

/* Контент поверх GIF */
.hero__container--overlay {
    position: relative;
    z-index: 1;
    /* 🔹 Контент выше фона */
}

.hero__container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.hero__title {
    font-size: 56px;
    font-weight: 800;
    line-height: 1.1;
    margin: 0 0 24px;
    letter-spacing: -1px;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
    /* 🔹 Тень для читаемости */
}

.hero__highlight {
    color: #ffffff;
    border-bottom: 4px solid #ffffff;
}

.hero__subtitle {
    font-size: 20px;
    opacity: 0.9;
    margin: 0 0 32px;
    line-height: 1.6;
    text-shadow: 0 1px 8px rgba(0, 0, 0, 0.4);
}

.hero__actions {
    display: flex;
    gap: 16px;
}

.hero__btn {
    padding: 14px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.hero__btn--primary {
    background: #ffffff;
    color: #000000;
}

.hero__btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 255, 255, 0.2);
}

.hero__btn--secondary {
    background: transparent;
    color: #ffffff;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.hero__btn--secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #ffffff;
}

.hero__placeholder {
    width: 100%;
    height: 400px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
}

/* Контейнер для картинки */
.hero__image {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hero__img {
  width: 100%;
  max-width: 520px;
  height: auto;
  border-radius: 24px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255,255,255,0.1);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  display: block;
}

/* Эффект при наведении */
.hero__image:hover .hero__img {
  transform: translateY(-8px) scale(1.02);
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
}

/* Декоративный фон за картинкой (опционально) */
.hero__image::before {
  content: '';
  position: absolute;
  width: 90%;
  height: 90%;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 24px;
  z-index: -1;
  transform: rotate(-3deg);
  border: 1px solid rgba(255,255,255,0.08);
}

/* Адаптивность */
@media (max-width: 968px) {
    .hero__container {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .hero__title {
        font-size: 40px;
    }

    .hero__actions {
        justify-content: center;
    }

    .hero__image {
        display: none;
    }

    .hero {
        min-height: 400px;
    }
}

@media (max-width: 640px) {
    .hero__title {
        font-size: 32px;
    }

    .hero__subtitle {
        font-size: 16px;
    }

    .hero__btn {
        padding: 12px 24px;
        font-size: 14px;
    }
}
</style>