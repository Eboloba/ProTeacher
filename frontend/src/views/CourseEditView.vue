<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'

const route = useRoute()
const router = useRouter()
const { pushStatus } = useToast()
const { currentUser } = useAuth()

const courseId = ref(route.params.id)
const isLoading = ref(false)
const isSaving = ref(false)

// Course data
const courseData = ref({
    title: '',
    description: '',
    what_you_learn: '',
    about_course: '',
    for_whom: '',
    price: 0,
    level: 'beginner',
    status: 'draft',
    certificate: 'TeacherPro',
})

// Modules
const modules = ref([])
const showModuleForm = ref(false)
const editingModule = ref(null)
const moduleForm = ref({
    title: '',
    sort_order: 0,
})

// Lessons
const showLessonForm = ref(false)
const editingLesson = ref(null)
const activeModuleId = ref(null)
const lessonForm = ref({
    title: '',
    content_type: 'text', // 'text' | 'video'
    lesson_type: 'lesson', // 'lesson' | 'quiz'
    content: '',
    video_url: '',
    sort_order: 0,
})

// === QUIZ: Управление вопросами ===
const quizQuestions = ref([])
const isQuizLoading = ref(false)

// Добавить новый пустой вопрос
function addQuestion() {
    quizQuestions.value.push({
        id: null,
        question: '',
        options: ['', '', ''],
        correct_answer: [0],
        sort_order: quizQuestions.value.length,
    })
}

// Удалить вопрос по индексу
function removeQuestion(index) {
    quizQuestions.value.splice(index, 1)
    // Пересчитываем порядковые номера
    quizQuestions.value.forEach((q, idx) => { q.sort_order = idx })
}

// Загрузить вопросы теста для урока
async function loadQuizQuestions(lessonId) {
    if (!lessonId) return []

    isQuizLoading.value = true
    try {
        const res = await api.getQuizQuestions(lessonId)
        const questions = res.questions || []

        // 🔹 Нормализуем correct_answer в массив
        return questions.map(q => {
            let correct = q.correct_answer
            // Если строка вида "[0,2]" — парсим
            if (typeof correct === 'string' && correct.startsWith('[')) {
                try { correct = JSON.parse(correct) } catch { correct = [correct] }
            }
            // Если число или строка "0" — оборачиваем в массив
            if (!Array.isArray(correct)) {
                correct = [correct]
            }
            // Если options не массив — инициализируем
            if (!Array.isArray(q.options)) {
                q.options = JSON.parse(q.options || '["", ""]')
            }
            return { ...q, correct_answer: correct }
        })
    } catch (err) {
        console.error('Failed to load quiz:', err)
        return []
    } finally {
        isQuizLoading.value = false
    }
}

// Сохранить вопросы теста для урока
async function saveQuizQuestions(lessonId, questions) {
    if (!lessonId || !questions.length) return

    for (const q of questions) {
        if (!q.question.trim()) {
            pushStatus('Вопрос не может быть пустым', 'error')
            throw new Error('Empty question')
        }
        if (!Array.isArray(q.options) || q.options.filter(o => o.trim()).length < 2) {
            pushStatus('Укажите минимум 2 варианта ответа', 'error')
            throw new Error('Not enough options')
        }


        let correctAnswer = q.correct_answer
        if (Array.isArray(correctAnswer)) {

            if (q.options.length <= 2 && correctAnswer.length > 1) {
                correctAnswer = correctAnswer[0]
            }
            correctAnswer = JSON.stringify(correctAnswer)
        }

        const payload = {
            question: q.question.trim(),
            options: q.options.map(o => o.trim()).filter(o => o),
            correct_answer: correctAnswer,
            sort_order: q.sort_order,
        }

        if (q.id) {
            await api.updateQuizQuestion(q.id, payload)
        } else {
            await api.createQuizQuestion(lessonId, payload)
        }
    }
}

// Load course
async function loadCourse() {
    isLoading.value = true
    try {
        // Запрашиваем данные у API
        const res = await api.getCourse(courseId.value)

        // Заполняем форму данными курса
        courseData.value = {
            ...res.course,
            price: res.course.price || 0,
        }

        // Обрабатываем модули и уроки
        if (res.modules && Array.isArray(res.modules)) {
            modules.value = res.modules.map(module => ({
                id: module.id,
                title: module.title,
                sort_order: module.sort_order || 0,
                // Преобразуем уроки внутри модуля
                lessons: (module.lessons || []).map(lesson => ({
                    id: lesson.id,
                    title: lesson.title,
                    content_type: lesson.content_type || 'text',
                    lesson_type: lesson.lesson_type || 'lesson',
                    content: lesson.content || '',
                    video_url: lesson.video_url || '',
                    sort_order: lesson.sort_order || 0,
                }))
            }))
        } else {
            modules.value = []
        }

        pushStatus('Курс загружен', 'success')
    } catch (err) {
        console.error('Failed to load course:', err)
        pushStatus('Не удалось загрузить курс', 'error')
    } finally {
        isLoading.value = false
    }
}

// Save course
async function saveCourse() {
    if (!courseData.value.title.trim()) {
        pushStatus('Введите название курса', 'error')
        return
    }

    isSaving.value = true
    try {
        const payload = { ...courseData.value }
        // Если курс уже на проверке или опубликован, не сбрасываем статус на черновик
        if (payload.status !== 'pending' && payload.status !== 'published') {
            payload.status = 'draft'
        }

        await api.updateCourse(courseId.value, payload)
        courseData.value.status = payload.status
        pushStatus('Курс сохранен', 'success')
    } catch (err) {
        pushStatus('Ошибка сохранения: ' + err.message, 'error')
    } finally {
        isSaving.value = false
    }
}

// Module functions
function openModuleForm(module = null, moduleId = null) {
    if (module) {
        editingModule.value = module
        moduleForm.value = {
            title: module.title,
            sort_order: module.sort_order,
        }
    } else {
        editingModule.value = null
        moduleForm.value = {
            title: '',
            sort_order: modules.value.length,
        }
    }
    activeModuleId.value = moduleId
    showModuleForm.value = true
}

function closeModuleForm() {
    showModuleForm.value = false
    editingModule.value = null
    moduleForm.value = { title: '', sort_order: 0 }
}

async function saveModule() {
    if (!moduleForm.value.title.trim()) {
        pushStatus('Введите название модуля', 'error')
        return
    }

    try {
        if (editingModule.value) {
            // Update existing module
            await api.updateModule(editingModule.value.id, moduleForm.value)
            editingModule.value.title = moduleForm.value.title
            editingModule.value.sort_order = moduleForm.value.sort_order
            pushStatus('Модуль обновлен', 'success')
        } else {
            // Create new module
            const res = await api.createModule(courseId.value, moduleForm.value)
            modules.value.push({
                id: res.id,
                title: moduleForm.value.title,
                sort_order: moduleForm.value.sort_order,
                lessons: [],
            })
            pushStatus('Модуль создан', 'success')
        }
        closeModuleForm()
    } catch (err) {
        pushStatus('Ошибка: ' + err.message, 'error')
    }
}

async function deleteModule(moduleId) {
    if (!confirm('Удалить модуль и все его уроки?')) return

    try {
        await api.deleteModule(moduleId)
        modules.value = modules.value.filter(m => m.id !== moduleId)
        pushStatus('Модуль удален', 'success')
    } catch (err) {
        pushStatus('Ошибка удаления: ' + err.message, 'error')
    }
}

// === Открытие формы урока ===
async function openLessonForm(moduleId, lesson = null) {
    activeModuleId.value = moduleId
    isQuizLoading.value = false

    // Сбрасываем форму
    if (lesson) {
        editingLesson.value = lesson
        lessonForm.value = {
            title: lesson.title,
            content_type: lesson.content_type || 'text',
            lesson_type: lesson.lesson_type || 'lesson',
            content: lesson.content || '',
            video_url: lesson.video_url || '',
            sort_order: lesson.sort_order || 0,
        }

        // 🔹 Если это тест — загружаем вопросы
        if (lesson.lesson_type === 'quiz' && lesson.id) {
            quizQuestions.value = await loadQuizQuestions(lesson.id)
        } else {
            quizQuestions.value = []
        }
    } else {
        editingLesson.value = null
        const module = modules.value.find(m => m.id === moduleId)
        lessonForm.value = {
            title: '',
            content_type: 'text',
            lesson_type: 'lesson',
            content: '',
            video_url: '',
            sort_order: module ? module.lessons.length : 0,
        }
        quizQuestions.value = []
    }

    showLessonForm.value = true
}

// === Закрытие формы урока ===
function closeLessonForm() {
    showLessonForm.value = false
    editingLesson.value = null
    quizQuestions.value = []
    lessonForm.value = {
        title: '',
        content_type: 'text',
        lesson_type: 'lesson',
        content: '',
        video_url: '',
        sort_order: 0,
    }
}

// === Удаление курса ===
async function deleteCourse() {
    if (!confirm('⚠️ Вы уверены?\nКурс будет удалён без возможности восстановления.')) {
        return
    }

    try {
        // 🔹 Используем метод для удаления своего курса
        await api.deleteOwnCourse(courseId.value)
        pushStatus('Курс удалён', 'success')
        router.push('/teaching')
    } catch (err) {
        pushStatus('Ошибка: ' + (err.message || 'Не удалось удалить курс'), 'error')
    }
}

function toggleCorrectAnswer(question, optionIndex, event) {
    // Гарантируем, что correct_answer — массив
    if (!Array.isArray(question.correct_answer)) {
        question.correct_answer = []
    }

    const isRadio = question.options.length <= 2
    const isChecked = event.target.checked

    if (isRadio) {
        // 🔹 Радио-режим: только один правильный ответ
        if (isChecked) {
            question.correct_answer = [optionIndex]
        }
    } else {
        // 🔹 Чекбокс-режим: можно несколько правильных
        const idx = question.correct_answer.indexOf(optionIndex)
        if (isChecked) {
            if (!question.correct_answer.includes(optionIndex)) {
                question.correct_answer.push(optionIndex)
            }
        } else {
            if (idx > -1) {
                question.correct_answer.splice(idx, 1)
            }
        }
    }
}

function addOption(question) {
    if (!Array.isArray(question.options)) question.options = ['']
    question.options.push('')
    if (!Array.isArray(question.correct_answer)) question.correct_answer = []
}
function removeOption(question, index) {
    question.options.splice(index, 1);
    if (Array.isArray(question.correct_answer)) {
        question.correct_answer = question.correct_answer
            .map(i => i > index ? i - 1 : i)
            .filter(i => i !== index);
    }
}

// === Сохранение урока ===
async function saveLesson() {
    if (!lessonForm.value.title.trim()) {
        pushStatus('Введите название урока', 'error')
        return
    }

    try {
        let lessonId = editingLesson.value?.id

        // 1. Сохраняем сам урок
        if (editingLesson.value) {
            await api.updateLesson(lessonId, lessonForm.value)

            // Обновляем локально
            const module = modules.value.find(m => m.id === activeModuleId.value)
            const lesson = module.lessons.find(l => l.id === lessonId)
            if (lesson) Object.assign(lesson, lessonForm.value)

            pushStatus('Урок обновлен', 'success')
            loadCourse()
        } else {
            const res = await api.createLesson(activeModuleId.value, lessonForm.value)
            lessonId = res.id

            const module = modules.value.find(m => m.id === activeModuleId.value)
            module.lessons.push({
                id: lessonId,
                ...lessonForm.value,
            })

            pushStatus('Урок создан', 'success')
            loadCourse()
        }

        // 2. 🔹 Если это тест — сохраняем вопросы (только если lessonId есть!)
        if (lessonForm.value.lesson_type === 'quiz' && lessonId && quizQuestions.value.length > 0) {
            await saveQuizQuestions(lessonId, quizQuestions.value)
            pushStatus('Вопросы теста сохранены', 'success')
        }

        closeLessonForm()

    } catch (err) {
        console.error('Save lesson error:', err)
        pushStatus('Ошибка: ' + (err.message || 'Не удалось сохранить'), 'error')
        throw err // Пробрасываем ошибку вверх, чтобы не закрывать модалку при ошибке
    }
}

async function deleteLesson(lessonId, moduleId) {
    if (!confirm('Удалить этот урок?')) return

    try {
        await api.deleteLesson(lessonId)

        const module = modules.value.find(m => m.id === moduleId)
        module.lessons = module.lessons.filter(l => l.id !== lessonId)

        pushStatus('Урок удален', 'success')
    } catch (err) {
        pushStatus('Ошибка удаления: ' + err.message, 'error')
    }
}

// Publish course
async function submitForReview() {
    if (!courseData.value.title.trim()) {
        pushStatus('Введите название курса перед отправкой', 'error')
        return
    }
    if (courseData.value.status === 'pending') {
        pushStatus('Курс уже отправлен на проверку', 'info')
        return
    }

    isSaving.value = true
    try {
        await api.updateCourse(courseId.value, {
            ...courseData.value,
            status: 'pending'
        })
        courseData.value.status = 'pending'
        pushStatus('Курс отправлен на проверку 📤', 'success')
    } catch (err) {
        pushStatus('Ошибка: ' + err.message, 'error')
    } finally {
        isSaving.value = false
    }
}

onMounted(() => {
    loadCourse()
})
</script>

<template>
    <div class="course-edit">
        <div class="course-edit__container">
            <!-- Sidebar -->
            <aside class="course-edit__sidebar">
                <div class="course-edit__sidebar-header">
                    <h2 class="course-edit__sidebar-title">Редактор курса</h2>
                </div>

                <nav class="course-edit__nav">
                    <a href="#basic" class="course-edit__nav-link">Основная информация</a>
                    <a href="#modules" class="course-edit__nav-link">Программа курса</a>
                </nav>

                <div class="course-edit__sidebar-footer">
                    <div class="course-edit__status" :class="courseData.status">
                        {{
                            courseData.status === 'published' ? 'Опубликован' :
                                courseData.status === 'pending' ? 'На проверке' : 'Черновик'
                        }}
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="course-edit__main" v-if="!isLoading">
                <!-- Basic Info -->
                <section id="basic" class="course-edit__section">
                    <h1 class="course-edit__title">Основная информация</h1>

                    <div class="course-edit__form">
                        <div class="course-edit__group">
                            <label class="course-edit__label">Название курса *</label>
                            <input v-model="courseData.title" type="text" placeholder="Например: Полный курс по Python"
                                class="course-edit__input" />
                        </div>

                        <div class="course-edit__group">
                            <label class="course-edit__label">Краткое описание</label>
                            <textarea v-model="courseData.description" rows="3"
                                placeholder="Краткое описание курса для карточки"
                                class="course-edit__textarea"></textarea>
                        </div>

                        <div class="course-edit__row">
                            <div class="course-edit__group">
                                <label class="course-edit__label">Уровень</label>
                                <select v-model="courseData.level" class="course-edit__select">
                                    <option value="beginner">Начальный</option>
                                    <option value="intermediate">Средний</option>
                                    <option value="advanced">Продвинутый</option>
                                </select>
                            </div>

                            <div class="course-edit__group">
                                <label class="course-edit__label">Цена (₽)</label>
                                <input v-model.number="courseData.price" type="number" min="0" step="0.01"
                                    placeholder="0 для бесплатного" class="course-edit__input" />
                            </div>
                        </div>

                        <div class="course-edit__group">
                            <label class="course-edit__label">Чему вы научитесь (каждая строка - отдельный
                                пункт)</label>
                            <textarea v-model="courseData.what_you_learn" rows="5"
                                placeholder="Создавать веб-приложения&#10;Работать с базами данных&#10;Писать тесты"
                                class="course-edit__textarea"></textarea>
                        </div>

                        <div class="course-edit__group">
                            <label class="course-edit__label">О курсе (подробное описание)</label>
                            <textarea v-model="courseData.about_course" rows="8"
                                placeholder="Подробное описание курса..." class="course-edit__textarea"></textarea>
                        </div>

                        <div class="course-edit__group">
                            <label class="course-edit__label">Для кого этот курс</label>
                            <textarea v-model="courseData.for_whom" rows="4"
                                placeholder="Для начинающих программистов&#10;Для тех, кто хочет сменить профессию"
                                class="course-edit__textarea"></textarea>
                        </div>
                    </div>
                </section>

                <!-- Modules & Lessons -->
                <section id="modules" class="course-edit__section">
                    <div class="course-edit__section-header">
                        <h1 class="course-edit__title">Программа курса</h1>
                        <button class="course-edit__btn course-edit__btn--secondary" @click="openModuleForm()">
                            + Добавить модуль
                        </button>
                    </div>

                    <div v-if="modules.length === 0" class="course-edit__empty">
                        <p>В курсе пока нет модулей</p>
                        <button class="course-edit__btn course-edit__btn--primary" @click="openModuleForm()">
                            Создать первый модуль
                        </button>
                    </div>

                    <div v-else class="course-edit__modules">
                        <div v-for="module in modules" :key="module.id" class="course-edit__module">
                            <div class="course-edit__module-header">
                                <div class="course-edit__module-title-wrapper">
                                    <span class="course-edit__module-number">{{ module.sort_order + 1 }}</span>
                                    <h3 class="course-edit__module-title">{{ module.title }}</h3>
                                </div>
                                <div class="course-edit__module-actions">
                                    <button class="course-edit__icon-btn" @click="openModuleForm(module)"
                                        title="Редактировать модуль">
                                        ✏️
                                    </button>
                                    <button class="course-edit__icon-btn course-edit__icon-btn--danger"
                                        @click="deleteModule(module.id)" title="Удалить модуль">
                                        🗑️
                                    </button>
                                    <button class="course-edit__btn course-edit__btn--small"
                                        @click="openLessonForm(module.id)">
                                        + Урок
                                    </button>
                                </div>
                            </div>

                            <div v-if="module.lessons.length === 0" class="course-edit__module-empty">
                                <p>В модуле нет уроков</p>
                            </div>

                            <div v-else class="course-edit__lessons">
                                <div v-for="lesson in module.lessons" :key="lesson.id" class="course-edit__lesson">
                                    <div class="course-edit__lesson-info">
                                        <span class="course-edit__lesson-icon">
                                            {{ lesson.lesson_type === 'quiz' ? '📝' : lesson.content_type === 'video' ?
                                                '🎥' : '📄' }}
                                        </span>
                                        <div>
                                            <h4 class="course-edit__lesson-title">{{ lesson.title }}</h4>
                                            <span class="course-edit__lesson-type">
                                                {{ lesson.lesson_type === 'quiz' ? 'Тест' : lesson.content_type ===
                                                    'video' ? 'Видео' : 'Текст' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="course-edit__lesson-actions">
                                        <button class="course-edit__icon-btn"
                                            @click="openLessonForm(module.id, lesson)">
                                            ✏️
                                        </button>
                                        <button class="course-edit__icon-btn course-edit__icon-btn--danger"
                                            @click="deleteLesson(lesson.id, module.id)">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="course-edit__group">
                            <label class="course-edit__label">Сертификат</label>
                            <div class="certificate-options">
                                <label class="certificate-option">
                                    <input type="radio" v-model="courseData.certificate" value="TeacherPro" />
                                    <span>🎓 Выдать сертификат TeacherPro</span>
                                </label>
                                <label class="certificate-option">
                                    <input type="radio" v-model="courseData.certificate" value="" />
                                    <span>❌ Не выдавать сертификат</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <div v-else class="course-edit__loading">
                <div class="course-edit__loader"></div>
                <p>Загрузка курса...</p>
            </div>
        </div>

        <!-- Module Modal -->
        <Transition name="modal">
            <div v-if="showModuleForm" class="modal-backdrop" @click.self="closeModuleForm">
                <div class="modal-window">
                    <h3 class="modal-title">
                        {{ editingModule ? 'Редактировать модуль' : 'Новый модуль' }}
                    </h3>

                    <div class="modal-content">
                        <div class="modal-group">
                            <label class="modal-label">Название модуля</label>
                            <input v-model="moduleForm.title" type="text" placeholder="Например: Основы Python"
                                class="modal-input" @keyup.enter="saveModule" />
                        </div>

                        <div class="modal-group">
                            <label class="modal-label">Порядок</label>
                            <input v-model.number="moduleForm.sort_order" type="number" min="0" class="modal-input" />
                        </div>

                    </div>

                    <div class="modal-actions">
                        <button class="modal-btn modal-btn--ghost" @click="closeModuleForm">
                            Отмена
                        </button>
                        <button class="modal-btn modal-btn--primary" @click="saveModule">
                            {{ editingModule ? 'Сохранить' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Lesson Modal -->
        <Transition name="modal">
            <div v-if="showLessonForm" class="modal-backdrop" @click.self="closeLessonForm">
                <div class="modal-window modal-window--large">
                    <h3 class="modal-title">
                        {{ editingLesson ? 'Редактировать урок' : 'Новый урок' }}
                    </h3>

                    <div class="modal-content">
                        <div class="modal-group">
                            <label class="modal-label">Название урока *</label>
                            <input v-model="lessonForm.title" type="text"
                                placeholder="Например: Переменные и типы данных" class="modal-input" />
                        </div>

                        <div class="modal-row">
                            <div class="modal-group">
                                <label class="modal-label">Тип контента</label>
                                <select v-model="lessonForm.content_type" class="modal-select">
                                    <option value="text">Текст</option>
                                    <option value="video">Видео</option>
                                </select>
                            </div>

                            <div class="modal-group">
                                <label class="modal-label">Тип урока</label>
                                <select v-model="lessonForm.lesson_type" class="modal-select">
                                    <option value="lesson">Урок</option>
                                    <option value="quiz">Тест</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="lessonForm.content_type === 'text'" class="modal-group">
                            <label class="modal-label">Содержание урока</label>
                            <textarea v-model="lessonForm.content" rows="10"
                                placeholder="Текст урока...&#10;&#10;Можно использовать Markdown:&#10;# Заголовок&#10;**жирный текст**&#10;`код`"
                                class="modal-textarea"></textarea>
                        </div>

                        <div v-else class="modal-group">
                            <label class="modal-label">Ссылка на видео</label>
                            <input v-model="lessonForm.video_url" type="url"
                                placeholder="https://youtube.com/watch?v=..." class="modal-input" />
                        </div>

                        <div class="modal-group">
                            <label class="modal-label">Порядок</label>
                            <input v-model.number="lessonForm.sort_order" type="number" min="0" class="modal-input" />
                        </div>

                        <!-- === БЛОК ТЕСТА: показывается только если lesson_type === 'quiz' === -->
                        <template v-if="lessonForm.lesson_type === 'quiz'">
                            <div class="modal-section">
                                <div class="modal-section__header">
                                    <h4 class="modal-subtitle">Вопросы теста</h4>
                                    <button class="modal-btn modal-btn--small modal-btn--secondary" @click="addQuestion"
                                        :disabled="isQuizLoading">
                                        + Вопрос
                                    </button>
                                </div>

                                <!-- Загрузка -->
                                <div v-if="isQuizLoading" class="quiz-loading">
                                    <div class="quiz-loader"></div>
                                    <span>Загрузка вопросов...</span>
                                </div>

                                <!-- Список вопросов -->
                                <template v-else>
                                    <div v-if="quizQuestions.length === 0" class="quiz-empty">
                                        <p>Нет вопросов. Добавьте первый, чтобы начать.</p>
                                    </div>

                                    <div v-for="(q, qIndex) in quizQuestions" :key="qIndex" class="quiz-question">

                                        <!-- Заголовок вопроса -->
                                        <div class="quiz-question__header">
                                            <span class="quiz-question__number">Вопрос {{ qIndex + 1 }}</span>
                                            <button class="quiz-question__delete" @click="removeQuestion(qIndex)"
                                                title="Удалить вопрос">✕</button>
                                        </div>

                                        <!-- Текст вопроса -->
                                        <div class="modal-group">
                                            <label class="modal-label">Текст вопроса *</label>
                                            <input v-model="q.question" type="text"
                                                placeholder="Например: Что выводит console.log(2 + 2)?"
                                                class="modal-input" />
                                        </div>

                                        <!-- Варианты ответов (🔹 ИСПРАВЛЕНО: q вместо question) -->
                                        <div v-for="(option, oIndex) in q.options" :key="oIndex"
                                            class="quiz-option-row">

                                            <input :type="q.options.length <= 2 ? 'radio' : 'checkbox'"
                                                :name="`question-${q.id || qIndex}`" :value="oIndex"
                                                :checked="Array.isArray(q.correct_answer) ? q.correct_answer.includes(oIndex) : false"
                                                @change="toggleCorrectAnswer(q, oIndex, $event)"
                                                class="quiz-option__input" />

                                            <input v-model="q.options[oIndex]" placeholder="Вариант ответа"
                                                class="quiz-option__text" />

                                            <button class="quiz-remove-btn" @click="removeOption(q, oIndex)"
                                                type="button">✕</button>
                                        </div>

                                        <!-- 🔹 ИСПРАВЛЕНО: передаём q (объект), а не qIndex -->
                                        <button class="quiz-question__add-option" @click="addOption(q)">
                                            + Добавить вариант
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="modal-actions">
                            <button class="modal-btn modal-btn--ghost" @click="closeLessonForm">
                                Отмена
                            </button>
                            <button class="modal-btn modal-btn--primary" @click="saveLesson">
                                {{ editingLesson ? 'Сохранить' : 'Создать' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- 🔹 ЕДИНЫЙ БЛОК ДЕЙСТВИЙ (в самом низу страницы) -->
        <footer class="course-edit__footer">
            <div class="course-edit__footer-inner">

                <!-- Левая часть: отмена и удаление -->
                <div class="course-edit__footer-left">
                    <button class="course-edit__btn course-edit__btn--ghost" @click="router.push('/teaching')">
                        ← Назад
                    </button>

                    <button v-if="courseId" class="course-edit__btn course-edit__btn--danger" @click="deleteCourse">
                        🗑️ Удалить
                    </button>
                </div>

                <!-- Правая часть: сохранение и публикация -->
                <div class="course-edit__footer-right">
                    <button class="course-edit__btn course-edit__btn--secondary" @click="saveCourse"
                        :disabled="isSaving">
                        {{ isSaving ? '...' : '💾 Сохранить' }}
                    </button>

                    <button v-if="courseData.status !== 'published'" class="course-edit__btn course-edit__btn--primary"
                        @click="submitForReview" :disabled="isSaving || courseData.status === 'pending'">
                        {{ courseData.status === 'pending' ? '⏳ На проверке' : '📤 На проверку' }}
                    </button>
                </div>

            </div>
        </footer>
    </div>
</template>

<style scoped>
.course-edit {
    min-height: calc(100vh - 64px);
    background: #f7fafc;
}

.course-edit__container {
    display: grid;
    grid-template-columns: 280px 1fr;
    max-width: 1400px;
    margin: 0 auto;
}

/* Sidebar */
.course-edit__sidebar {
    background: #000000;
    color: #ffffff;
    padding: 24px;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 64px;
    height: calc(100vh - 64px);
    overflow-y: auto;
}

.course-edit__sidebar-header {
    margin-bottom: 32px;
}

.course-edit__back {
    background: transparent;
    border: 1px solid #333;
    color: #fff;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    margin-bottom: 16px;
    transition: all 0.2s;
}

.course-edit__back:hover {
    background: #1a1a1a;
}

.course-edit__sidebar-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
}

.course-edit__nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.course-edit__nav-link {
    color: #a0aec0;
    text-decoration: none;
    padding: 12px 16px;
    border-radius: 8px;
    transition: all 0.2s;
    font-size: 14px;
}

.course-edit__nav-link:hover {
    background: #1a1a1a;
    color: #fff;
}

.course-edit__sidebar-footer {
    margin-top: auto;
    padding-top: 24px;
    border-top: 1px solid #1a1a1a;
}

.course-edit__status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
}

.course-edit__status.draft {
    background: #fed7d7;
    color: #822727;
}

.course-edit__status.published {
    background: #c6f6d5;
    color: #22543d;
}

.course-edit__btn--publish {
    width: 100%;
    background: #48bb78;
    color: #fff;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.course-edit__btn--publish:hover {
    background: #38a169;
}

/* Статус "На проверке" */
.course-edit__status.pending {
    background: #edf2f7;
    color: #2d3748;
    border: 2px dashed #a0aec0;
}

/* Блокировка кнопки при ожидании */
.course-edit__btn--primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #000000;
}

/* 🔹 Футер с кнопками (фиксированный или в потоке) */
.course-edit__footer {
    position: sticky;
    bottom: 0;
    left: 0;
    right: 0;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 16px 0;
    z-index: 100;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.05);
}

.course-edit__footer-inner {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.course-edit__footer-left,
.course-edit__footer-right {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Кнопки */
.course-edit__btn {
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    white-space: nowrap;
}

/* Отмена / Назад */
.course-edit__btn--ghost {
    background: transparent;
    color: #718096;
    border: 1px solid #e2e8f0;
}

.course-edit__btn--ghost:hover {
    background: #f7fafc;
    color: #000;
    border-color: #000;
}

/* Черновик */
.course-edit__btn--secondary {
    background: #fff;
    color: #000;
    border: 2px solid #000;
}

.course-edit__btn--secondary:hover {
    background: #f7fafc;
}

/* Публикация / На проверку */
.course-edit__btn--primary {
    background: #000;
    color: #fff;
}

.course-edit__btn--primary:hover {
    background: #2d2d2d;
}

.course-edit__btn--primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Удаление (опасное действие) */
.course-edit__btn--danger {
    background: #fff;
    color: #e53e3e;
    border: 2px solid #fed7d7;
}

.course-edit__btn--danger:hover {
    background: #fff5f5;
    border-color: #e53e3e;
}

.certificate-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    background: #f7fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.certificate-option {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: background 0.2s;
}

.certificate-option:hover {
    background: #fff;
}

.certificate-option input[type="radio"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #000;
}

.certificate-option span {
    font-size: 14px;
    color: #2d3748;
}

/* Адаптивность */
@media (max-width: 768px) {
    .course-edit__footer-inner {
        flex-direction: column;
        gap: 12px;
    }

    .course-edit__footer-left,
    .course-edit__footer-right {
        width: 100%;
        justify-content: center;
    }
}

/* === Стили для вариантов ответа в тесте === */
.quiz-option-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s;
}

.quiz-option-row:hover {
    border-color: #000000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.quiz-option__input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #000000;
    flex-shrink: 0;
}

.quiz-option__text {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.quiz-option__text:focus {
    outline: none;
    border-color: #000000;
}

.quiz-remove-btn {
    background: none;
    border: none;
    color: #718096;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quiz-remove-btn:hover {
    background: #fff5f5;
    color: #e53e3e;
}

.quiz-type-hint {
    margin: 8px 0 16px;
    font-size: 13px;
    color: #718096;
    font-style: italic;
}

.hint-multi {
    color: #48bb78;
}

.hint-single {
    color: #4a5568;
}

/* Адаптивность для мобильных */
@media (max-width: 640px) {
    .quiz-option-row {
        flex-wrap: wrap;
    }

    .quiz-option__text {
        order: 3;
        width: 100%;
        margin-top: 8px;
    }
}

.course-edit__btn--danger {
    background: #fff;
    color: #e53e3e;
    border: 2px solid #fed7d7;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.course-edit__btn--danger:hover {
    background: #fff5f5;
    border-color: #e53e3e;
    transform: translateY(-1px);
}

.course-edit__danger-hint {
    margin-top: 12px;
    font-size: 12px;
    color: #718096;
    line-height: 1.4;
}

/* Main */
.course-edit__main {
    padding: 40px;
}

.course-edit__section {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    margin-bottom: 32px;
    border: 1px solid #e2e8f0;
}

.course-edit__section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.course-edit__title {
    font-size: 32px;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 32px;
}

/* Form */
.course-edit__form {
    max-width: 800px;
}

.course-edit__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.course-edit__group {
    margin-bottom: 28px;
}

.course-edit__label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
}

.course-edit__input,
.course-edit__textarea,
.course-edit__select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 15px;
    transition: border-color 0.2s;
    font-family: inherit;
}

.course-edit__input:focus,
.course-edit__textarea:focus,
.course-edit__select:focus {
    outline: none;
    border-color: #000000;
}

.course-edit__textarea {
    resize: vertical;
    min-height: 100px;
}

.course-edit__hint {
    display: block;
    margin-top: 6px;
    font-size: 13px;
    color: #718096;
}

.course-edit__actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 32px;
    padding-top: 32px;
    border-top: 1px solid #e2e8f0;
}

/* Buttons */
.course-edit__btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.course-edit__btn--primary {
    background: #000000;
    color: #fff;
}

.course-edit__btn--primary:hover:not(:disabled) {
    background: #2d2d2d;
}

.course-edit__btn--primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.course-edit__btn--secondary {
    background: #f7fafc;
    color: #000000;
    border: 1px solid #e2e8f0;
}

.course-edit__btn--secondary:hover {
    background: #edf2f7;
}

.course-edit__btn--ghost {
    background: transparent;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.course-edit__btn--ghost:hover {
    background: #f7fafc;
}

.course-edit__btn--small {
    padding: 6px 12px;
    font-size: 13px;
}

/* Modules */
.course-edit__modules {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.course-edit__module {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.course-edit__module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: #f7fafc;
    border-bottom: 1px solid #e2e8f0;
}

.course-edit__module-title-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.course-edit__module-number {
    width: 32px;
    height: 32px;
    background: #000000;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.course-edit__module-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a202c;
    margin: 0;
}

.course-edit__module-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.course-edit__module-empty {
    padding: 24px;
    text-align: center;
    color: #718096;
}

/* Lessons */
.course-edit__lessons {
    padding: 16px 24px;
}

.course-edit__lesson {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.course-edit__lesson:hover {
    border-color: #000000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.course-edit__lesson:last-child {
    margin-bottom: 0;
}

.course-edit__lesson-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.course-edit__lesson-icon {
    font-size: 24px;
}

.course-edit__lesson-title {
    font-size: 15px;
    font-weight: 600;
    color: #1a202c;
    margin: 0 0 4px;
}

.course-edit__lesson-type {
    font-size: 13px;
    color: #718096;
}

.course-edit__lesson-actions {
    display: flex;
    gap: 8px;
}

.course-edit__icon-btn {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 6px;
    font-size: 18px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.course-edit__icon-btn:hover {
    background: #f7fafc;
}

.course-edit__icon-btn--danger:hover {
    background: #fff5f5;
}

.modal-section {
    margin: 24px 0;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.modal-subtitle {
    font-size: 16px;
    font-weight: 600;
    color: #1a202c;
    margin: 0 0 16px;
}

/* === Секция теста === */
.modal-section {
    margin: 24px 0;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.modal-section__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.modal-subtitle {
    font-size: 16px;
    font-weight: 600;
    color: #1a202c;
    margin: 0;
}

.modal-btn--small {
    padding: 6px 12px;
    font-size: 13px;
}

/* Загрузка */
.quiz-loading {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 24px;
    color: #718096;
    justify-content: center;
}

.quiz-loader {
    width: 20px;
    height: 20px;
    border: 2px solid #e2e8f0;
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Пустое состояние */
.quiz-empty {
    padding: 24px;
    text-align: center;
    color: #718096;
    background: #f7fafc;
    border-radius: 8px;
    border: 1px dashed #cbd5e0;
}

/* Вопрос */
.quiz-question {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 16px;
}

.quiz-question__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.quiz-question__number {
    font-weight: 600;
    color: #1a202c;
    font-size: 14px;
}

.quiz-question__delete {
    background: none;
    border: none;
    color: #e53e3e;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}

.quiz-question__delete:hover {
    background: #fff5f5;
}

/* Варианты ответов */
.quiz-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 12px 0;
}

.quiz-option {
    display: flex;
    align-items: center;
    gap: 10px;
}

.quiz-option__remove {
    background: none;
    border: none;
    color: #718096;
    font-size: 20px;
    cursor: pointer;
    padding: 0 8px;
    transition: color 0.2s;
}

.quiz-option__remove:hover {
    color: #e53e3e;
}

.quiz-question__add-option {
    width: 100%;
    background: transparent;
    border: 1px dashed #cbd5e0;
    color: #4a5568;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
}

.quiz-question__add-option:hover {
    border-color: #000000;
    color: #000000;
    background: #f7fafc;
}

/* Адаптивность */
@media (max-width: 640px) {
    .quiz-option {
        flex-wrap: wrap;
    }

    .quiz-option__correct {
        order: -1;
    }
}

.modal-input--small {
    padding: 8px 12px;
    font-size: 14px;
}

/* Empty */
.course-edit__empty {
    text-align: center;
    padding: 60px 20px;
    background: #f7fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
}

.course-edit__empty p {
    color: #718096;
    margin: 0 0 20px;
    font-size: 16px;
}

/* Loading */
.course-edit__loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 100px 20px;
}

.course-edit__loader {
    width: 48px;
    height: 48px;
    border: 4px solid #e2e8f0;
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Modal */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}

.modal-window {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-window--large {
    max-width: 700px;
}

.modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #1a202c;
    margin: 0;
    padding: 24px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-content {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.modal-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.modal-group {
    margin-bottom: 20px;
}

.modal-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
}

.modal-input,
.modal-select,
.modal-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 15px;
    transition: border-color 0.2s;
    font-family: inherit;
}

.modal-input:focus,
.modal-select:focus,
.modal-textarea:focus {
    outline: none;
    border-color: #000000;
}

.modal-textarea {
    resize: vertical;
    min-height: 150px;
}

.modal-actions {
    padding: 20px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.modal-btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.modal-btn--primary {
    background: #000000;
    color: #fff;
}

.modal-btn--primary:hover {
    background: #2d2d2d;
}

.modal-btn--ghost {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #4a5568;
}

.modal-btn--ghost:hover {
    background: #f7fafc;
}

/* Modal transitions */
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .modal-window,
.modal-leave-to .modal-window {
    transform: scale(0.95) translateY(-20px);
}

/* Responsive */
@media (max-width: 968px) {
    .course-edit__container {
        grid-template-columns: 1fr;
    }

    .course-edit__sidebar {
        position: static;
        height: auto;
    }

    .course-edit__main {
        padding: 24px;
    }

    .course-edit__section {
        padding: 24px;
    }

    .course-edit__row,
    .modal-row {
        grid-template-columns: 1fr;
    }
}
</style>