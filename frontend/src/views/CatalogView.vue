<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import CourseCard from '../components/ui/CourseCard.vue'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'

const { currentUser } = useAuth()
const route = useRoute()
const router = useRouter()
const { pushStatus } = useToast()

const dbCourses = ref([])
const searchQuery = ref('')
const selectedCategory = ref('all')
const priceFilter = ref('all')
const showFreeOnly = ref(false)
const showLanguageMenu = ref(false)
const selectedLanguage = ref('На любом языке')

const categories = [
  { value: 'all', label: 'Все категории' },
  { value: 'programming', label: 'Программирование' },
  { value: 'data', label: 'Анализ данных' },
  { value: 'web', label: 'Веб-разработка' },
  { value: 'devops', label: 'DevOps' },
  { value: 'ai', label: 'Искусственный интеллект' },
  { value: 'testing', label: 'Тестирование' },
  { value: 'ege', label: 'ЕГЭ и ОГЭ' },
]

const languageOptions = ["На любом языке", "На русском", "На английском"]

const categoryKeywords = {
  programming: ['python', 'java', 'c++', 'c#', 'javascript', 'php', 'go', 'rust'],
  data: ['данных', 'анализ', 'ml', 'machine learning', 'sql', 'excel', 'tableau'],
  web: ['веб', 'frontend', 'backend', 'html', 'css', 'vue', 'react', 'angular'],
  devops: ['devops', 'docker', 'kubernetes', 'ci/cd', 'linux', 'terraform'],
  ai: ['ai', 'ии', 'нейросет', 'chatgpt', 'llm', 'искусственный интеллект'],
  testing: ['тест', 'qa', 'pytest', 'selenium', 'playwright', 'автотест'],
  ege: ['егэ', 'огэ', 'школа', 'подготовка'],
}

function mapCourse(raw, index) {
  const rawPrice = parseFloat(raw.price ?? raw.cost ?? 0)
  const isFree = rawPrice === 0 || raw.is_free === true
  const displayPrice = isFree ? 'Бесплатно' : `${rawPrice} ₽`
  const buttonText = isFree ? 'Пройти' : 'Купить'
  
  const stats = [
    raw.rating ? `★ ${raw.rating}` : '★ 5',
    raw.students ? `👤 ${raw.students}` : `👤 ${100 + index * 9}`,
    raw.duration ? `👁 ${raw.duration}ч` : `👁 ${4 + index}ч`
  ].join('   ')

  let category = 'programming'
  const titleLower = (raw.title + ' ' + (raw.description || '')).toLowerCase()
  for (const [cat, keywords] of Object.entries(categoryKeywords)) {
    if (keywords.some(k => titleLower.includes(k))) {
      category = cat
      break
    }
  }

  return {
    id: raw.id ?? index,
    title: raw.title || "Без названия",
    author: raw.teacher_name || raw.author || "Автор курса",
    description: raw.description || "",
    stats,
    price: rawPrice,
    displayPrice,
    is_free: isFree,
    button_text: buttonText,
    category,
    rating: raw.rating ?? 5,
    students: raw.students ?? (100 + index * 9),
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

// 🔹 Функция для платных курсов — показываем инфо о покупке
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

const filteredCourses = computed(() => {
  let result = dbCourses.value.map(mapCourse)

  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(c =>
      c.title.toLowerCase().includes(query) ||
      c.author.toLowerCase().includes(query) ||
      c.description.toLowerCase().includes(query)
    )
  }

  if (selectedCategory.value !== 'all') {
    result = result.filter(c => c.category === selectedCategory.value)
  }

  if (priceFilter.value === 'free') {
    result = result.filter(c => c.is_free)
  } else if (priceFilter.value === 'paid') {
    result = result.filter(c => !c.is_free)
  }

  if (showFreeOnly.value) {
    result = result.filter(c => c.is_free)
  }

  return result
})

async function loadCourses() {
  try {
    const res = await api.getCourses()
    dbCourses.value = Array.isArray(res.courses) ? res.courses : []
    
    // Apply URL params
    if (route.query.category) {
      selectedCategory.value = route.query.category
    }
    if (route.query.search) {
      searchQuery.value = route.query.search
    }
  } catch (err) {
    console.error('Failed to load courses:', err)
    pushStatus('Не удалось загрузить курсы', 'error')
  }
}

function applyFilters() {
  const query = { ...route.query }
  
  if (searchQuery.value) query.search = searchQuery.value
  else delete query.search
  
  if (selectedCategory.value !== 'all') query.category = selectedCategory.value
  else delete query.category
  
  if (priceFilter.value !== 'all') query.price = priceFilter.value
  else delete query.price
  
  router.push({ query })
  pushStatus(`Найдено курсов: ${filteredCourses.value.length}`, 'info')
}

function resetFilters() {
  searchQuery.value = ''
  selectedCategory.value = 'all'
  priceFilter.value = 'all'
  showFreeOnly.value = false
  router.push({ query: {} })
  loadCourses()
}

function handleCourseAction(course) {
  pushStatus(course.is_free ? 'Запись на курс...' : `Переход к оплате: ${course.displayPrice}`, 'info')
}

onMounted(() => {
  loadCourses()
})
</script>

<template>
  <div class="catalog">
    <div class="catalog__container">
      <!-- Search Panel -->
      <section class="catalog__search">
        <input 
          v-model="searchQuery" 
          class="catalog__search-input" 
          placeholder="Название курса, автор или предмет"
          @keyup.enter="applyFilters" 
        />
        
        <div class="catalog__filters">
          <div class="catalog__filter-group">
            <label>Категория:</label>
            <select v-model="selectedCategory">
              <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                {{ cat.label }}
              </option>
            </select>
          </div>

          <div class="catalog__filter-group">
            <label>Цена:</label>
            <select v-model="priceFilter">
              <option value="all">Все</option>
              <option value="free">Бесплатные</option>
              <option value="paid">Платные</option>
            </select>
          </div>

          <label class="catalog__checkbox">
            <input type="checkbox" v-model="showFreeOnly" />
            Только бесплатные
          </label>
          
          <button class="catalog__btn catalog__btn--ghost" @click="resetFilters">
            Сбросить
          </button>
        </div>
      </section>

      <!-- Results -->
      <section class="catalog__results">
        <div class="catalog__results-header">
          <h2>Результаты поиска</h2>
          <span class="catalog__count">Найдено: <strong>{{ filteredCourses.length }}</strong></span>
        </div>

        <div v-if="filteredCourses.length === 0" class="catalog__empty">
          <div class="catalog__empty-icon">🔍</div>
          <p>По вашему запросу ничего не найдено</p>
          <button class="catalog__btn catalog__btn--ghost" @click="resetFilters">
            Сбросить фильтры
          </button>
        </div>

        <div v-else class="catalog__grid">
          <CourseCard 
            v-for="course in filteredCourses" 
            :key="course.id"
            :course="course"
            @click="router.push(`/course/${course.id}`)"
            @start="startLearning"
            @buy="handleBuy"
          />
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.catalog {
  min-height: 100vh;
  background: #f7fafc;
  padding: 40px 0;
}

.catalog__container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Search */
.catalog__search {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 32px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  border: 1px solid #e2e8f0;
}

.catalog__search-input {
  width: 100%;
  padding: 14px 20px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 16px;
  margin-bottom: 20px;
}

.catalog__search-input:focus {
  outline: none;
  border-color: #000000;
}

.catalog__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-end;
}

.catalog__filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.catalog__filter-group label {
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
}

.catalog__filter-group select {
  padding: 10px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  background: white;
  min-width: 180px;
}

.catalog__checkbox {
  font-size: 14px;
  color: #4a5568;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.catalog__checkbox input {
  width: 18px;
  height: 18px;
}

.catalog__btn {
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.catalog__btn--primary {
  background: #000000;
  color: white;
}

.catalog__btn--primary:hover {
  background: #2d2d2d;
}

.catalog__btn--ghost {
  background: transparent;
  border: 1px solid #e2e8f0;
  color: #4a5568;
}

.catalog__btn--ghost:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

/* Results */
.catalog__results-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.catalog__results-header h2 {
  font-size: 28px;
  font-weight: 800;
  color: #1a202c;
  margin: 0;
}

.catalog__count {
  font-size: 14px;
  color: #718096;
}

.catalog__count strong {
  color: #000000;
  font-weight: 700;
}

.catalog__empty {
  text-align: center;
  padding: 80px 20px;
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
}

.catalog__empty-icon {
  font-size: 64px;
  margin-bottom: 20px;
}

.catalog__empty p {
  color: #718096;
  font-size: 16px;
  margin-bottom: 24px;
}

.catalog__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 24px;
}

@media (max-width: 768px) {
  .catalog__filters {
    flex-direction: column;
    align-items: stretch;
  }
  
  .catalog__filter-group select {
    width: 100%;
  }
  
  .catalog__results-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
}
</style>