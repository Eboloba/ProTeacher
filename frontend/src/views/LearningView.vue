<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const { pushStatus } = useToast()

const learningState = ref({
    courseId: null,
    courseTitle: '',
    modules: [],
    progress: { total: 0, completed: 0, percent: 0 },
    currentLessonId: null,
    currentLesson: null,
    quiz: null,
    quizAnswers: {},
    quizSubmitted: false,
    quizResult: null,
})

async function loadCourseStructure() {
    try {
        const structure = await api.getCourseStructure(route.params.courseId)

        learningState.value = {
            courseId: structure.course.id,
            courseTitle: structure.course.title,
            modules: structure.modules,
            progress: structure.progress,
            currentLessonId: null,
            currentLesson: null,
            quiz: null,
            quizAnswers: {},
            quizSubmitted: false,
            quizResult: null,
        }

        if (structure.modules.length > 0 && structure.modules[0].lessons.length > 0) {
            await loadLesson(structure.modules[0].lessons[0].id)
        }
    } catch (err) {
        pushStatus('Ошибка: ' + err.message, 'error')
    }
}

function parseOptions(options) {
    if (!options) return []
    if (Array.isArray(options)) return options
    try {
        return JSON.parse(options)
    } catch {
        return []
    }
}

// Текст типа урока для отображения
function getLessonTypeText(lesson) {
    if (!lesson) return 'Урок'
    if (lesson.lesson_type === 'quiz') return 'Тест'
    if (lesson.content_type === 'video') return 'Видео'
    return 'Текст'
}

// Определяем, является ли текущий урок видео
const isVideoLesson = computed(() =>
    learningState.value.currentLesson?.content_type === 'video'
)

// Парсим ссылку и возвращаем готовый объект для рендера
const videoUrl = computed(() => {
    const lesson = learningState.value.currentLesson
    const url = lesson?.video_url?.trim()

    if (!url) return { isValid: false }

    // YouTube (watch, embed, shorts, youtu.be)
    const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/)
    if (yt) return {
        isValid: true,
        type: 'iframe',
        embedUrl: `https://www.youtube.com/embed/${yt[1]}?modestbranding=1&rel=0&enablejsapi=1`
    }

    // Vimeo
    const vimeo = url.match(/vimeo\.com\/(\d+)/)
    if (vimeo) return {
        isValid: true,
        type: 'iframe',
        embedUrl: `https://player.vimeo.com/video/${vimeo[1]}?color=000000&title=0&byline=0&portrait=0`
    }

    // Прямой файл (.mp4, .webm, .ogg)
    if (url.match(/\.(mp4|webm|ogg)(\?.*)?$/i)) {
        return { isValid: true, type: 'direct', directUrl: url }
    }

    // Фолбэк: если ссылка валидна, но не распознана
    return { isValid: true, type: 'iframe', embedUrl: url }
})

async function checkAndIssueCertificate() {
    if (learningState.value.progress.percent < 100) return

    try {
        const res = await api.issueCertificate(
            learningState.value.courseId,
            learningState.value.courseTitle
        )

        if (res.issued) {
            pushStatus(`🎉 Поздравляем! Вы получили сертификат: ${res.certificate.code}`, 'success', 6000)
            // Можно добавить модалку с предпросмотром сертификата
        }
    } catch (err) {
        console.error('Certificate issue failed:', err)
    }
}



// 🔹 Безопасный парсер JSON (защита от ошибок при парсинге)
function safeParse(str, fallback = []) {
    if (!str) return fallback
    if (typeof str !== 'string') return str
    try {
        return JSON.parse(str)
    } catch (e) {
        console.warn('Failed to parse JSON:', str)
        return fallback
    }
}

async function loadLesson(lessonId) {
    try {
        const res = await api.getLesson(lessonId)
        learningState.value.currentLessonId = lessonId
        learningState.value.currentLesson = res.lesson
        learningState.value.quiz = res.quiz
        learningState.value.quizSubmitted = false
        learningState.value.quizResult = null
        learningState.value.quizAnswers = {}

        if (res.lesson.lesson_type === 'quiz') {
            res.quiz.forEach((q, index) => {
                const options = safeParse(q.options, [])
                learningState.value.quizAnswers[index] = options.length > 2 ? [] : null
            })
        } else {
            learningState.value.quiz = null
        }

        await updateProgress()
    } catch (err) {
        pushStatus('Не удалось загрузить урок: ' + err.message, 'error')
    }
}

async function updateProgress() {
    try {
        const structure = await api.getCourseStructure(learningState.value.courseId)
        learningState.value.modules = structure.modules
        learningState.value.progress = structure.progress
    } catch (err) {
        console.error('Failed to update progress:', err)
    }
}

function isLessonCompleted(lessonId) {
    for (const module of learningState.value.modules) {
        const lesson = module.lessons.find(l => l.id === lessonId)
        if (lesson) return lesson.completed
    }
    return false
}

async function markLessonComplete() {
    try {
        await api.completeLesson(learningState.value.currentLessonId)
        pushStatus('Урок пройден! 🎉', 'success')
        await updateProgress()


        await checkAndIssueCertificate()
    } catch (err) {
        pushStatus('Ошибка: ' + err.message, 'error')
    }
}

async function submitQuiz() {
    const quiz = learningState.value.quiz
    const answers = learningState.value.quizAnswers

    console.log(learningState.value.quizAnswers);


    const totalQuestions = quiz?.length || 0
    if (totalQuestions === 0) {
        pushStatus('В тесте нет вопросов', 'error')
        return
    }

    const answeredCount = Object.values(answers).filter(
        a => a !== undefined && a !== null && (Array.isArray(a) ? a.length > 0 : a !== '')
    ).length

    if (answeredCount < totalQuestions) {
        pushStatus(`⚠️ Выберите хотя бы 1 вариант ответа!`, 'error')
        return
    }

    const formattedAnswers = {}
    quiz.forEach((q, index) => {
        const userAnswer = answers[index]
        // 🔹 Гарантируем массив чисел: [0] или [0, 2]
        if (Array.isArray(userAnswer)) {
            formattedAnswers[q.id] = userAnswer.map(i => Number(i))
        } else {
            formattedAnswers[q.id] = [Number(userAnswer)]
        }
    })

    try {
        const res = await api.completeLesson(learningState.value.currentLessonId, formattedAnswers)

        learningState.value.quizSubmitted = true
        learningState.value.quizResult = res

        if (res.success) {
            pushStatus('🎉 Урок пройден!', 'success')
            await updateProgress()
            await checkAndIssueCertificate()
        } else {
            pushStatus(`😕 Правильно: ${res.correct_count}/${res.total}. Попробуйте ещё раз`, 'error')
        }
    } catch (err) {
        pushStatus('Ошибка: ' + (err.message || 'Не удалось отправить ответы'), 'error')
    }
}

function retryQuiz() {
    learningState.value.quizSubmitted = false
    learningState.value.quizResult = null
    learningState.value.quizAnswers = {}
}

function nextLesson() {
    let found = false
    for (const module of learningState.value.modules) {
        for (const lesson of module.lessons) {
            if (found) {
                loadLesson(lesson.id)
                return
            }
            if (lesson.id === learningState.value.currentLessonId) {
                found = true
            }
        }
    }
    pushStatus('Курс завершён! 🎉', 'success')
}


function handleAnswerChange(questionIndex, optionIndex, event) {
    const quiz = learningState.value.quiz
    const question = quiz[questionIndex]
    const options = safeParse(question.options, [])
    const isMulti = options.length > 2

    // 🔹 Гарантируем, что optionIndex — число
    const optIdx = Number(optionIndex)

    if (isMulti) {
        // 🔹 Чекбокс-режим: массив чисел
        if (!Array.isArray(learningState.value.quizAnswers[questionIndex])) {
            learningState.value.quizAnswers[questionIndex] = []
        }

        const answers = learningState.value.quizAnswers[questionIndex]
        const idx = answers.indexOf(optIdx)

        if (event.target.checked) {
            if (!answers.includes(optIdx)) {
                answers.push(optIdx)  // 🔹 Число, не строка
            }
        } else {
            if (idx > -1) {
                answers.splice(idx, 1)
            }
        }
    } else {
        // 🔹 Радио-режим: одно число
        learningState.value.quizAnswers[questionIndex] = optIdx
    }
}

function formatLessonText(text) {
    if (!text) return ''

    return text.split(/\n\n+/).map(block => {
        block = block.trim()
        if (!block) return ''

        if (block.startsWith('# ')) {
            return `<h2>${block.slice(2)}</h2>`
        }
        if (block.startsWith('## ')) {
            return `<h3>${block.slice(3)}</h3>`
        }

        if (block.startsWith('```')) {
            const code = block.replace(/```/g, '').trim()
            return `<pre><code>${code}</code></pre>`
        }

        if (/^[\-\•]\s/.test(block)) {
            const items = block.split(/\n/)
                .filter(line => /^[\-\•]\s/.test(line.trim()))
                .map(line => `<li>${line.trim().replace(/^[\-\•]\s+/, '')}</li>`)
                .join('')
            return `<ul>${items}</ul>`
        }

        block = block.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        block = block.replace(/\*(.+?)\*/g, '<em>$1</em>')
        block = block.replace(/`(.+?)`/g, '<code>$1</code>')

        return `<p>${block.replace(/\n/g, '<br>')}</p>`

    }).join('\n')
}

function exitLearning() {
    router.push('/')
}

onMounted(() => {
    loadCourseStructure()
})
</script>

<template>
    <div class="learning">
        <!-- Sidebar -->
        <aside class="learning__sidebar">
            <div class="learning__course-info">
                <h2 class="learning__course-title">{{ learningState.courseTitle }}</h2>
                <div class="learning__progress-bar">
                    <div class="learning__progress-fill" :style="{ width: learningState.progress.percent + '%' }"></div>
                </div>
                <div class="learning__progress-text">
                    {{ learningState.progress.completed }} из {{ learningState.progress.total }} шагов пройдено
                </div>
            </div>

            <nav class="learning__modules">
                <div v-for="module in learningState.modules" :key="module.id" class="learning__module">
                    <div class="learning__module-header">
                        <span class="learning__module-title">{{ module.title }}</span>
                    </div>
                    <ul class="learning__lessons">
                        <li v-for="lesson in module.lessons" :key="lesson.id" class="learning__lesson" :class="{
                            active: learningState.currentLessonId === lesson.id,
                            completed: lesson.completed
                        }" @click="loadLesson(lesson.id)">
                            <span class="learning__lesson-icon">
                                {{ lesson.lesson_type === 'quiz' ? '📝' : '📄' }}
                            </span>
                            <span class="learning__lesson-title">{{ lesson.title }}</span>
                            <span v-if="lesson.completed" class="learning__checkmark">✓</span>
                        </li>
                    </ul>
                </div>
            </nav>

            <button class="learning__exit" @click="exitLearning">
                ← Выйти из курса
            </button>
        </aside>

        <!-- Main Content -->
        <main class="learning__main">
            <div v-if="learningState.currentLesson" class="learning__lesson-viewer">

                <!-- Заголовок урока -->
                <div class="learning__lesson-header">
                    <h1>{{ learningState.currentLesson.title }}</h1>
                    <div class="learning__lesson-meta">
                        <span class="learning__lesson-type">
                            {{ getLessonTypeText(learningState.currentLesson) }}
                        </span>
                    </div>
                </div>

                <!-- === УРОК С ТЕСТОМ  === -->
                <template v-if="learningState.currentLesson.lesson_type === 'quiz'">

                    <div v-if="learningState.currentLesson.content" class="learning__lesson-text"
                        v-html="formatLessonText(learningState.currentLesson.content)">
                    </div>

                    <!-- Затем показываем тест -->
                    <div class="learning__quiz-section">
                        <div v-if="!learningState.quizSubmitted">
                            <div v-for="(question, qIndex) in learningState.quiz" :key="question.id"
                                class="learning__quiz-question">
                                <h3>{{ qIndex + 1 }}. {{ question.question }}</h3>
                                <div class="learning__quiz-options">
                                    <label v-for="(option, oIndex) in parseOptions(question.options)" :key="oIndex"
                                        class="learning__quiz-option">
                                        <input :type="parseOptions(question.options).length <= 2 ? 'radio' : 'checkbox'"
                                            :name="`question-${question.id}`" :value="oIndex"
                                            v-model="learningState.quizAnswers[qIndex]"
                                            @change="handleAnswerChange(qIndex, oIndex, $event)"
                                            class="learning__quiz-input" />
                                        <span>{{ option }}</span>
                                    </label>
                                </div>
                            </div>

                            <button class="learning__btn learning__btn--primary" @click="submitQuiz">
                                Отправить ответы
                            </button>
                        </div>

                        <div v-else class="learning__quiz-result">
                            <div v-if="learningState.quizResult?.success" class="learning__result-success">
                                <h2>🎉 Поздравляем!</h2>
                                <p>Вы успешно прошли тест!</p>
                            </div>
                            <div v-else class="learning__result-error">
                                <h2>😕 Попробуйте ещё раз</h2>
                                <p>Правильно ответов: {{ learningState.quizResult?.correct_count }} из {{
                                    learningState.quizResult?.total }}</p>
                            </div>

                            <button v-if="!learningState.quizResult?.success"
                                class="learning__btn learning__btn--primary" @click="retryQuiz">
                                Пройти заново
                            </button>
                            <button v-else class="learning__btn learning__btn--primary" @click="nextLesson">
                                Следующий урок →
                            </button>
                        </div>
                    </div>
                </template>

                <!-- === ОБЫЧНЫЙ УРОК  === -->
                <template v-else>

                    <!-- Видео-контент -->
                    <template v-if="learningState.currentLesson.content_type === 'video'">
                        <div v-if="videoUrl.isValid" class="learning__video-container">
                            <iframe v-if="videoUrl.type === 'iframe'" :src="videoUrl.embedUrl"
                                class="learning__video-frame" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen></iframe>
                            <video v-else :src="videoUrl.directUrl" controls preload="metadata"
                                class="learning__video-native"></video>
                        </div>

                        <div v-else class="learning__video-error">
                            <div class="learning__video-error__icon">⚠️</div>
                            <p class="learning__video-error__text">Видео не найдено или ссылка не поддерживается</p>
                            <small class="learning__video-error__hint">
                                Вставьте ссылку на YouTube, Vimeo или прямой файл (.mp4, .webm)
                            </small>
                        </div>
                    </template>

                    <!-- Текстовый контент -->
                    <template v-else>
                        <div class="learning__lesson-text"
                            v-html="formatLessonText(learningState.currentLesson.content || 'Содержимое урока...')">
                        </div>
                    </template>

                    <!-- Кнопка завершения для обычных уроков -->
                    <div class="learning__completion-wrapper">
                        <button v-if="!isLessonCompleted(learningState.currentLessonId)"
                            class="learning__btn learning__btn--primary" @click="markLessonComplete">
                            Отметить как пройденное
                        </button>
                        <div v-else class="learning__completed">
                            ✓ Урок пройден
                        </div>
                    </div>

                </template>

            </div>

            <div v-else class="learning__loading">
                <div class="learning__loader"></div>
                <p>Загрузка урока...</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.learning {
    display: grid;
    grid-template-columns: 380px 1fr;
    height: calc(100vh - 64px);
    background: #f7fafc;
    overflow: hidden;
}

/* Sidebar */
.learning__sidebar {
    background: #000000;
    color: #fff;
    padding: 24px;
    overflow-y: auto;
    border-right: 1px solid #1a1a1a;
    display: flex;
    flex-direction: column;
}

.learning__course-info {
    margin-bottom: 24px;
    flex-shrink: 0;
}

.learning__course-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 16px;
    color: #fff;
    line-height: 1.4;
}

.learning__progress-bar {
    height: 6px;
    background: #2d2d2d;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 8px;
}

.learning__progress-fill {
    height: 100%;
    background: #48bb78;
    transition: width 0.3s ease;
}

.learning__progress-text {
    font-size: 12px;
    color: #a0aec0;
}

.learning__modules {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 20px;
}

.learning__module {
    margin-bottom: 16px;
}

.learning__module-header {
    padding: 12px 16px;
    background: #1a1a1a;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    color: #e0e0e0;
}

.learning__lessons {
    list-style: none;
    padding: 0;
    margin: 0;
}

.learning__lesson {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    cursor: pointer;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
    color: #a0aec0;
    margin-bottom: 4px;
}

.learning__lesson:hover {
    background: #1a1a1a;
    color: #fff;
}

.learning__lesson.active {
    background: #ffffff;
    color: #000000;
}

.learning__lesson.completed {
    color: #48bb78;
}

.learning__lesson-icon {
    font-size: 16px;
    flex-shrink: 0;
}

.learning__lesson-title {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.learning__completion-wrapper {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
}

.learning__completed {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: #c6f6d5;
    color: #22543d;
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
}

.learning__checkmark {
    color: #48bb78;
    font-weight: 700;
    flex-shrink: 0;
}

.learning__lesson.active .learning__checkmark {
    color: #000000;
}

.learning__exit {
    width: 100%;
    padding: 12px;
    background: transparent;
    border: 1px solid #ffffff;
    color: #ffffff;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s;
    flex-shrink: 0;
}

.learning__exit:hover {
    background: #ffffff;
    color: #000000;
}

.learning__lesson-text {
    background: #fff;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    line-height: 1.6;
}

.learning__lesson-text h1,
.learning__lesson-text h2,
.learning__lesson-text h3 {
    margin-top: 24px;
    margin-bottom: 16px;
    color: #1a202c;
}

.learning__lesson-text p {
    margin-bottom: 16px;
    color: #4a5568;
}

.learning__lesson-text code {
    background: #f7fafc;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    color: #e53e3e;
}

.learning__lesson-text pre {
    background: #1a202c;
    color: #fff;
    padding: 16px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 16px 0;
}

.learning__lesson-text pre code {
    background: transparent;
    color: #fff;
}

.learning__quiz-section {
    background: #f7fafc;
    padding: 32px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

/* Main */
.learning__main {
    padding: 40px;
    overflow-y: auto;
    background: #fff;
}

.learning__lesson-viewer {
    max-width: 900px;
    margin: 0 auto;
}

.learning__lesson-header {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 2px solid #e2e8f0;
}

.learning__lesson-header h1 {
    font-size: 32px;
    margin: 0 0 12px;
    color: #1a202c;
}

.learning__lesson-meta {
    display: flex;
    gap: 12px;
}

.learning__lesson-type {
    padding: 6px 16px;
    background: #000000;
    color: #ffffff;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.learning__lesson-text {
    font-size: 16px;
    line-height: 1.8;
    color: #2d3748;
}

.learning__lesson-text :deep(h2) {
    font-size: 24px;
    margin: 32px 0 16px;
    color: #1a202c;
}

.learning__lesson-text :deep(h3) {
    font-size: 20px;
    margin: 24px 0 12px;
    color: #1a202c;
}

.learning__lesson-text :deep(p) {
    margin-bottom: 16px;
}

.learning__lesson-text :deep(pre) {
    background: #f7fafc;
    padding: 16px;
    border-radius: 8px;
    overflow-x: auto;
    border: 1px solid #e2e8f0;
}

.learning__lesson-text :deep(code) {
    font-family: 'Courier New', monospace;
}

.learning__lesson-text :deep(ul) {
    margin: 16px 0;
    padding-left: 24px;
}

.learning__lesson-text :deep(li) {
    margin-bottom: 8px;
}

.learning__btn {
    margin-top: 32px;
    padding: 14px 32px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.learning__btn--primary {
    background: #000000;
    color: white;
}

.learning__btn--primary:hover {
    background: #2d2d2d;
}

.learning__completed {
    margin-top: 32px;
    padding: 20px;
    background: #c6f6d5;
    color: #22543d;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    font-size: 16px;
}

/* Quiz */
.learning__quiz-question {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

.learning__quiz-question h3 {
    margin: 0 0 20px;
    font-size: 18px;
    color: #1a202c;
}

.learning__quiz-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.learning__quiz-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.learning__quiz-option:hover {
    border-color: #000000;
    background: #f7fafc;
}

.learning__quiz-option input[type="radio"] {
    width: 20px;
    height: 20px;
}

.learning__quiz-result {
    text-align: center;
    padding: 48px;
    background: #f7fafc;
    border-radius: 12px;
    margin-top: 32px;
}

.learning__result-success h2 {
    color: #48bb78;
    margin: 0 0 12px;
}

.learning__result-error h2 {
    color: #f56565;
    margin: 0 0 12px;
}

.learning__loading {
    text-align: center;
    padding: 80px;
}

.learning__loader {
    width: 48px;
    height: 48px;
    border: 4px solid #e2e8f0;
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

.learning__quiz-input[type="checkbox"] {
    accent-color: #000000;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.learning__quiz-input[type="radio"] {
    accent-color: #000000;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.learning__quiz-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin-bottom: 8px;
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.learning__quiz-option:hover {
    border-color: #000000;
    background: #fff;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 968px) {
    .learning {
        grid-template-columns: 1fr;
    }

    .learning__sidebar {
        max-height: 300px;
    }
}

/* === Видео-плеер === */
.learning__video-section {
    margin: 32px 0;
    width: 100%;
}

.learning__video-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    /* Соотношение 16:9 */
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    border: 1px solid #e2e8f0;
}

.learning__video-frame,
.learning__video-native {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
    background: #000;
}

.learning__video-native {
    cursor: pointer;
    outline: none;
}

.learning__video-error {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    background: #f8fafc;
    border: 2px dashed #cbd5e0;
    border-radius: 16px;
    text-align: center;
    gap: 12px;
}

.learning__video-error__icon {
    font-size: 32px;
    opacity: 0.6;
}

.learning__video-error__text {
    font-size: 16px;
    font-weight: 600;
    color: #1a202c;
    margin: 0;
}

.learning__video-error__hint {
    font-size: 13px;
    color: #718096;
    margin: 0;
}
</style>