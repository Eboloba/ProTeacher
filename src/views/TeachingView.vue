<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../api'
import { useRouter } from 'vue-router'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { pushStatus } = useToast()
const { currentUser } = useAuth()

const teachingTab = ref('courses')
const currentTeacherView = ref('list')
const teacherCourses = ref([])
const selectedTeacherCourse = ref(null)
const isLoading = ref(false)

const newCourseForm = ref({
  title: '',
  description: '',
  price: 0,
  level: 'beginner',
})

const newLessonForm = ref({
  title: '',
  content_type: 'text',
  content: '',
  video_url: '',
})

const isTeacherComputed = computed(() => {
  return currentUser.value?.is_teacher === true || currentUser.value?.role === 'teacher'
})

async function loadTeacherCourses() {
  console.log('🔍 Загрузка курсов преподавателя...')
  console.log('Current user:', currentUser.value)
  console.log('Is teacher:', isTeacherComputed.value)
  
  if (!currentUser.value) {
    pushStatus('Пользователь не авторизован', 'error')
    return
  }

  if (!isTeacherComputed.value) {
    pushStatus('Только преподаватели могут просматривать курсы', 'error')
    console.warn('Пользователь не является преподавателем')
    return
  }

  isLoading.value = true
  try {
    console.log('📡 Запрос к API /teacher/courses...')
    const res = await api.getTeacherCourses()
    console.log('✅ Ответ от API:', res)
    
    // 🔹 Проверяем структуру ответа
    if (res.courses && Array.isArray(res.courses)) {
      teacherCourses.value = res.courses
      console.log(`📚 Загружено курсов: ${teacherCourses.value.length}`)
      console.log('Курсы:', teacherCourses.value)
      
      if (teacherCourses.value.length === 0) {
        pushStatus('У вас пока нет курсов', 'info')
      }
    } else if (Array.isArray(res)) {

      teacherCourses.value = res
      console.log(`📚 Загружено курсов (массив): ${teacherCourses.value.length}`)
    } else {
      console.error('❌ Неверная структура ответа:', res)
      pushStatus('Ошибка: неверный формат данных', 'error')
      teacherCourses.value = []
    }
  } catch (err) {
    console.error('❌ Ошибка загрузки курсов:', err)
    console.error('Full error:', err.message, err.response)
    pushStatus('Не удалось загрузить курсы: ' + err.message, 'error')
    teacherCourses.value = []
  } finally {
    isLoading.value = false
  }
}

async function createCourse() {
  if (!currentUser.value) {
    pushStatus('Войдите в аккаунт для создания курса', 'error')
    return
  }

  if (!isTeacherComputed.value) {
    pushStatus('Только преподаватели могут создавать курсы', 'error')
    return
  }

  if (!newCourseForm.value.title.trim()) {
    pushStatus('Введите название курса', 'error')
    return
  }

  pushStatus('Создание курса...', 'info')

  try {
    const res = await api.createCourse({
      title: newCourseForm.value.title,
      description: newCourseForm.value.description,
      price: parseFloat(newCourseForm.value.price) || 0,
      level: newCourseForm.value.level,
    })

    console.log('✅ Курс создан:', res)
    pushStatus('Курс создан!', 'success')
    currentTeacherView.value = 'list'
    newCourseForm.value = { title: '', description: '', price: 0, level: 'beginner' }
    await loadTeacherCourses()
  } catch (err) {
    console.error('❌ Ошибка создания курса:', err)
    pushStatus('Ошибка: ' + (err.message || 'Не удалось создать курс'), 'error')
  }
}

function openCourseEdit(course) {
  console.log('✏️ Переход к редактированию курса:', course)
  router.push(`/teaching/course/${course.id}/edit`)
}

async function addLesson() {
  if (!newLessonForm.value.title.trim()) {
    pushStatus('Введите название урока', 'error')
    return
  }

  pushStatus('Урок добавлен!', 'success')
  newLessonForm.value = { title: '', content_type: 'text', content: '', video_url: '' }
}

function deleteLesson(lessonId) {
  if (confirm('Удалить этот урок?')) {
    pushStatus('Урок удалён', 'success')
  }
}

onMounted(() => {
  console.log('🎯 TeachingView mounted')
  console.log('User:', currentUser.value)
  console.log('Is teacher:', isTeacherComputed.value)
  
  if (currentUser.value && isTeacherComputed.value) {
    loadTeacherCourses()
  } else {
    pushStatus('Требуется авторизация преподавателя', 'error')
  }
})
</script>

<template>
  <div class="teaching">
    <div class="teaching__container">
      <!-- Sidebar -->
      <aside class="teaching__sidebar">
        <div class="teaching__banner"></div>
        <button class="teaching__new-course" @click="currentTeacherView = 'create'">
          ＋ Новый курс
        </button>
        <nav class="teaching__nav">
          <a 
            :class="['teaching__nav-link', { active: teachingTab === 'courses' }]" 
            href="#"
            @click.prevent="teachingTab = 'courses'; currentTeacherView = 'list'; loadTeacherCourses()"
          >
            Курсы
          </a>
        </nav>
        <a class="teaching__help" href="#">◌ Помощь</a>
      </aside>

      <!-- Main Content -->
      <main class="teaching__main">
        <!-- Loading State -->
        <div v-if="isLoading" class="teaching__loading">
          <div class="teaching__loader"></div>
          <p>Загрузка курсов...</p>
        </div>

        <!-- Courses List -->
        <section v-else-if="teachingTab === 'courses' && currentTeacherView === 'list'">
          <h1 class="teaching__title">Мои курсы</h1>

          <!-- Debug Info -->
          <div class="teaching__debug" v-if="false">
            <p>Курсов: {{ teacherCourses.length }}</p>
            <pre>{{ teacherCourses }}</pre>
          </div>

          <div v-if="teacherCourses.length === 0" class="teaching__empty">
            <div class="teaching__empty-icon">📚</div>
            <h2>У вас пока нет курсов</h2>
            <p>Создайте свой первый курс и начните обучать студентов</p>
            <button class="teaching__btn teaching__btn--primary" @click="currentTeacherView = 'create'">
              ＋ Создать курс
            </button>
          </div>

          <div v-else class="teaching__courses-list">
            <div 
              v-for="course in teacherCourses" 
              :key="course.id" 
              class="teaching__course-card"
              @click="openCourseEdit(course)"
            >
              <div class="teaching__course-header">
                <div class="teaching__course-icon">📖</div>
                <div class="teaching__course-info">
                  <h3>{{ course.title }}</h3>
                  <p class="teaching__course-meta">
                    <span 
                      class="teaching__status" 
                      :class="course.status === 'published' ? 'teaching__status--published' : 'teaching__status--draft'"
                    >
                      {{ course.status === 'published' ? 'Опубликован' : 'Черновик' }}
                    </span>
                    <span>·</span>
                    <span>{{ new Date(course.created_at).toLocaleDateString('ru-RU') }}</span>
                  </p>
                </div>
              </div>
              <div class="teaching__course-actions">
                <span class="teaching__edit-hint">Нажмите для редактирования</span>
                <span class="teaching__arrow">→</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Create Course -->
        <section v-if="teachingTab === 'courses' && currentTeacherView === 'create'">
          <h1 class="teaching__title">Создание нового курса</h1>

          <form @submit.prevent="createCourse" class="teaching__form">
            <div class="teaching__group">
              <label class="teaching__label">Название курса *</label>
              <input 
                v-model="newCourseForm.title" 
                type="text" 
                placeholder="Например: Основы Python" 
                maxlength="64"
                required
                class="teaching__input"
              />
              <small class="teaching__hint">Максимум 64 символа</small>
            </div>

            <div class="teaching__group">
              <label class="teaching__label">Описание</label>
              <textarea 
                v-model="newCourseForm.description" 
                rows="4"
                placeholder="Опишите, чему научатся студенты..."
                class="teaching__textarea"
              ></textarea>
            </div>

            <div class="teaching__row">
              <div class="teaching__group">
                <label class="teaching__label">Уровень</label>
                <select v-model="newCourseForm.level" class="teaching__select">
                  <option value="beginner">Начальный</option>
                  <option value="intermediate">Средний</option>
                  <option value="advanced">Продвинутый</option>
                </select>
              </div>

              <div class="teaching__group">
                <label class="teaching__label">Цена (₽)</label>
                <input 
                  v-model="newCourseForm.price" 
                  type="number" 
                  min="0" 
                  step="0.01" 
                  placeholder="0 для бесплатного"
                  class="teaching__input"
                />
              </div>
            </div>

            <div class="teaching__actions">
              <button type="button" class="teaching__btn teaching__btn--ghost" @click="currentTeacherView = 'list'">
                Отмена
              </button>
              <button type="submit" class="teaching__btn teaching__btn--primary">
                Создать курс
              </button>
            </div>
          </form>
        </section>

        <!-- Course Content -->
        <section v-if="teachingTab === 'courses' && currentTeacherView === 'content' && selectedTeacherCourse">
          <div class="teaching__header">
            <button class="teaching__back" @click="currentTeacherView = 'list'">← Назад</button>
            <h1>{{ selectedTeacherCourse.title }}</h1>
          </div>
          <p>Редактирование курса (в разработке)</p>
        </section>
      </main>
    </div>
  </div>
</template>

<style scoped>
.teaching {
  min-height: calc(100vh - 64px);
  background: #f7fafc;
}

.teaching__container {
  max-width: 1280px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 260px 1fr;
  min-height: 100%;
}

/* Sidebar */
.teaching__sidebar {
  border-right: 1px solid #e2e8f0;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: white;
}

.teaching__banner {
  height: 70px;
  border-radius: 10px;
  background: linear-gradient(120deg, #f0f0f0, #e0e0e0, #d0d0d0);
}

.teaching__new-course {
  border: 1px solid #000000;
  background: #f7fafc;
  color: #000000;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  padding: 12px;
  text-align: left;
  cursor: pointer;
  transition: all 0.2s;
}

.teaching__new-course:hover {
  background: #000000;
  color: #ffffff;
}

.teaching__nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.teaching__nav-link {
  color: #4a5568;
  text-decoration: none;
  font-size: 14px;
  padding: 10px 12px;
  border-radius: 8px;
  transition: all 0.2s;
  font-weight: 500;
}

.teaching__nav-link:hover {
  background: #f7fafc;
  color: #2d3748;
}

.teaching__nav-link.active {
  color: #000000;
  font-weight: 700;
  background: #f7fafc;
}

.teaching__new-lesson {
  margin-top: 8px;
  color: #4a5568;
  text-decoration: none;
  font-size: 14px;
  padding: 8px 0;
}

.teaching__help {
  margin-top: auto;
  color: #718096;
  text-decoration: none;
  font-size: 14px;
}

/* Main */
.teaching__main {
  padding: 40px;
}

.teaching__title {
  font-size: 40px;
  font-weight: 800;
  margin: 0 0 32px;
  color: #1a202c;
}

/* Loading */
.teaching__loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
}

.teaching__loader {
  width: 48px;
  height: 48px;
  border: 4px solid #e2e8f0;
  border-top-color: #000000;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Empty State */
.teaching__empty {
  text-align: center;
  padding: 80px 20px;
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
}

.teaching__empty-icon {
  font-size: 64px;
  margin-bottom: 20px;
}

.teaching__empty h2 {
  font-size: 24px;
  margin: 0 0 12px;
  color: #1a202c;
}

.teaching__empty p {
  color: #718096;
  margin-bottom: 28px;
}

/* Courses List */
.teaching__courses-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.teaching__course-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.teaching__course-card:hover {
  border-color: #000000;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.teaching__course-header {
  display: flex;
  align-items: center;
  gap: 20px;
}

.teaching__course-icon {
  font-size: 36px;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f7fafc;
  border-radius: 10px;
}

.teaching__course-info h3 {
  margin: 0 0 6px;
  font-size: 20px;
  color: #1a202c;
}

.teaching__course-meta {
  font-size: 14px;
  color: #718096;
}

.teaching__status {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.teaching__status--published {
  background: #c6f6d5;
  color: #22543d;
}

.teaching__status--draft {
  background: #fed7d7;
  color: #822727;
}

.teaching__course-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.teaching__edit-hint {
  color: #a0aec0;
  font-size: 13px;
}

.teaching__arrow {
  font-size: 24px;
  color: #000000;
}

/* Form */
.teaching__form {
  max-width: 800px;
  background: white;
  padding: 40px;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
}

.teaching__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.teaching__group {
  margin-bottom: 28px;
}

.teaching__label {
  display: block;
  margin-bottom: 10px;
  font-size: 15px;
  font-weight: 600;
  color: #2d3748;
}

.teaching__input,
.teaching__textarea,
.teaching__select {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px 18px;
  font-size: 15px;
  transition: border-color 0.2s;
}

.teaching__input:focus,
.teaching__textarea:focus,
.teaching__select:focus {
  outline: none;
  border-color: #000000;
}

.teaching__textarea {
  font-family: inherit;
  resize: vertical;
}

.teaching__hint {
  display: block;
  color: #718096;
  font-size: 13px;
  margin-top: 8px;
}

.teaching__actions {
  display: flex;
  gap: 16px;
  justify-content: flex-end;
  margin-top: 32px;
  padding-top: 32px;
  border-top: 1px solid #e2e8f0;
}

.teaching__btn {
  padding: 14px 28px;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.teaching__btn--primary {
  background: #000000;
  color: white;
}

.teaching__btn--primary:hover {
  background: #2d2d2d;
}

.teaching__btn--ghost {
  background: transparent;
  border: 1px solid #e2e8f0;
  color: #4a5568;
}

.teaching__btn--ghost:hover {
  background: #f7fafc;
}

.teaching__header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 32px;
}

.teaching__back {
  padding: 10px 20px;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.2s;
}

.teaching__back:hover {
  background: #f7fafc;
}

.teaching__header h1 {
  flex: 1;
  margin: 0;
  font-size: 32px;
}

@media (max-width: 968px) {
  .teaching__container {
    grid-template-columns: 1fr;
  }
  
  .teaching__sidebar {
    border-right: none;
    border-bottom: 1px solid #e2e8f0;
  }
  
  .teaching__row {
    grid-template-columns: 1fr;
  }
}
</style>