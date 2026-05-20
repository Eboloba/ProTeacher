<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { useAuth } from '../composables/useAuth'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { currentUser } = useAuth()
const { pushStatus } = useToast()

const activeTab = ref('users')
const isLoading = ref(false)

const users = ref([])
const publishedCourses = ref([])
const pendingCourses = ref([])

// 🔹 Поиск и фильтры
const userSearch = ref('')
const userRoleFilter = ref('all') // 'all' | 'user' | 'teacher' | 'admin'

const courseSearch = ref('')
const courseLevelFilter = ref('all') // 'all' | 'beginner' | 'intermediate' | 'advanced'

// Модальные окна
const showUserModal = ref(false)
const showCourseModal = ref(false)
const editingUser = ref(null)
const editingCourse = ref(null)

// Формы
const userForm = ref({ first_name: '', last_name: '', email: '', role: 'user', is_teacher: false })
const courseForm = ref({ title: '', description: '', teacher_id: '', status: 'draft', price: 0, level: 'beginner' })
const teachersList = ref([])

// Защита доступа
onMounted(async () => {
    if (currentUser.value?.role !== 'admin') { router.push('/'); return }
    loadData()
})

async function loadData() {
    isLoading.value = true
    try {
        const [uRes, cRes, pRes] = await Promise.all([
            api.getAdminUsers(),
            api.getAdminCourses(),
            api.getPendingCourses()
        ])
        users.value = uRes.users || []
        publishedCourses.value = (cRes.courses || []).filter(c => c.status === 'published')
        pendingCourses.value = pRes.courses || []
        teachersList.value = users.value.filter(u => u.is_teacher)
    } catch (err) {
        pushStatus('Ошибка загрузки данных', 'error')
    } finally {
        isLoading.value = false
    }
}

// 🔹 Фильтрация пользователей
const filteredUsers = computed(() => {
    return users.value.filter(u => {
        const search = userSearch.value.toLowerCase()
        const matchSearch = !search ||
            u.first_name?.toLowerCase().includes(search) ||
            u.last_name?.toLowerCase().includes(search) ||
            u.email?.toLowerCase().includes(search)

        const matchRole = userRoleFilter.value === 'all' || u.role === userRoleFilter.value

        return matchSearch && matchRole
    })
})

// 🔹 Фильтрация курсов
const filteredCourses = computed(() => {
    return publishedCourses.value.filter(c => {
        const search = courseSearch.value.toLowerCase()
        const matchSearch = !search ||
            c.title?.toLowerCase().includes(search) ||
            c.teacher_name?.toLowerCase().includes(search) ||
            c.description?.toLowerCase().includes(search)

        const matchLevel = courseLevelFilter.value === 'all' || c.level === courseLevelFilter.value

        return matchSearch && matchLevel
    })
})


// Модерация курса
async function moderateCourse(id, status) {
    const course = pendingCourses.value.find(c => c.id === id)
    const courseTitle = course?.title || 'Курс'
    const teacherName = course?.teacher_name || 'Преподаватель'
    
    try {
        // 🔹 Передаём данные для уведомления
        await api.updateCourseStatus(id, status, {
            send_notification: true,
            course_title: courseTitle,
            teacher_name: teacherName
        })
        
        pushStatus(status === 'published' ? '✅ Курс опубликован' : '❌ Курс отклонён', 'success')
        pendingCourses.value = pendingCourses.value.filter(c => c.id !== id)
        
        if (status === 'published') {
            const updated = await api.getAdminCourses()
            publishedCourses.value = (updated.courses || []).filter(c => c.status === 'published')
        }
    } catch (err) {
        pushStatus('Ошибка модерации: ' + (err.message || ''), 'error')
    }
}

function openCourse(id) {
    router.push(`/learning/${id}`)
}

function isFree(price) {
    return parseFloat(price ?? 0) === 0
}

function getLevelText(level) {
    const levels = {
        'beginner': 'Начальный',
        'intermediate': 'Средний',
        'advanced': 'Продвинутый'
    }
    return levels[level] || level
}

// 🔹 Изменение роли пользователя
async function changeUserRole(userId, newRole) {
    // 🔹 Защита: нельзя изменить роль текущего пользователя
    if (userId === currentUser.value?.id) {
        pushStatus('❌ Нельзя изменить роль текущего пользователя. Выйдите из аккаунта и войдите под другим.', 'error')
        return
    }

    const user = users.value.find(u => u.id === userId)
    if (!user) return

    if (!confirm(`Изменить роль пользователя "${user.first_name} ${user.last_name}" с "${user.role}" на "${newRole}"?`)) {
        return
    }

    try {
        await api.updateUser(userId, { ...user, role: newRole })
        pushStatus(`Роль изменена на "${newRole}"`, 'success')
        loadData() // Перезагружаем данные
    } catch (err) {
        pushStatus('Ошибка: ' + (err.message || 'Не удалось изменить роль'), 'error')
    }
}

// --- USERS CRUD ---
function openUserModal(user = null) {
    editingUser.value = user
    userForm.value = user ? {
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        email: user.email || '',
        role: user.role || 'user',
        is_teacher: !!user.is_teacher
    } : {
        first_name: '', last_name: '', email: '', role: 'user', is_teacher: false
    }
    showUserModal.value = true
}

async function saveUser() {
    if (!userForm.value.email) return pushStatus('Email обязателен', 'error')
    try {
        if (editingUser.value) await api.updateUser(editingUser.value.id, userForm.value)
        else await api.createUser(userForm.value)
        pushStatus(editingUser.value ? 'Пользователь обновлён' : 'Пользователь создан', 'success')
        showUserModal.value = false
        loadData()
    } catch (err) { pushStatus(err.message, 'error') }
}

// --- COURSES CRUD ---
function openCourseModal(course = null) {
    editingCourse.value = course
    courseForm.value = course ? {
        ...course,
        teacher_id: course.teacher_id || teachersList.value[0]?.id || ''
    } : {
        title: '', description: '', teacher_id: teachersList.value[0]?.id || '', status: 'draft', price: 0, level: 'beginner'
    }
    showCourseModal.value = true
}

async function saveCourse() {
    if (!courseForm.value.title) return pushStatus('Название обязательно', 'error')
    try {
        if (editingCourse.value) await api.adminUpdateCourse(editingCourse.value.id, courseForm.value)
        else await api.createCourse(courseForm.value)
        pushStatus(editingCourse.value ? 'Курс обновлён' : 'Курс создан', 'success')
        showCourseModal.value = false
        loadData()
    } catch (err) { pushStatus(err.message, 'error') }
}

async function deleteUser(id) {
    if (!confirm('Удалить пользователя?')) return
    try {
        await api.deleteUser(id)
        users.value = users.value.filter(u => u.id !== id)
        pushStatus('Удалено', 'success')
    } catch (err) { pushStatus(err.message, 'error') }
}

async function deleteCourse(id) {
    if (!confirm('Удалить курс навсегда?')) return
    try {
        await api.deleteCourse(id)
        publishedCourses.value = publishedCourses.value.filter(c => c.id !== id)
        pushStatus('Курс удалён', 'success')
    } catch (err) { pushStatus(err.message, 'error') }
}

// 🔹 Сброс фильтров
function resetUserFilters() {
    userSearch.value = ''
    userRoleFilter.value = 'all'
}

function resetCourseFilters() {
    courseSearch.value = ''
    courseLevelFilter.value = 'all'
}
</script>

<template>
    <div class="admin">
        <aside class="admin__sidebar">
            <div class="sidebar__banner"><span>🛡</span> Админ Панель</div>
            <nav class="sidebar__nav">
                <button :class="['sidebar__link', { active: activeTab === 'users' }]" @click="activeTab = 'users'">
                    👥 Пользователи <span class="count">{{ filteredUsers.length }}</span>
                </button>
                <button :class="['sidebar__link', { active: activeTab === 'published' }]"
                    @click="activeTab = 'published'">
                    📚 Курсы <span class="count">{{ filteredCourses.length }}</span>
                </button>
                <button :class="['sidebar__link', { active: activeTab === 'pending' }]" @click="activeTab = 'pending'">
                    ⏳ На модерации <span class="count">{{ pendingCourses.length }}</span>
                </button>
            </nav>
        </aside>

        <main class="admin__main">
            <h1 class="admin__title">{{ activeTab === 'users' ? 'Пользователи' : activeTab === 'published' ?
                'Опубликованные курсы' : 'Курсы на проверке' }}</h1>

            <div v-if="isLoading" class="loading">
                <div class="loader"></div>Загрузка...
            </div>

            <template v-else>
                <!-- USERS TABLE с поиском и фильтрами -->
                <div v-if="activeTab === 'users'">
                    <!-- 🔹 Панель поиска и фильтров -->
                    <div class="admin__filters">
                        <div class="admin__search">
                            <input v-model="userSearch" type="text"
                                placeholder="Поиск по имени, фамилии или email..." class="admin__search-input" />
                            <button v-if="userSearch" class="admin__search-clear" @click="userSearch = ''">✕</button>
                        </div>

                        <div class="admin__filter-group">
                            <label class="admin__filter-label">Роль:</label>
                            <select v-model="userRoleFilter" class="admin__filter-select">
                                <option value="all">Все роли</option>
                                <option value="user">👤 Пользователь</option>
                                <option value="teacher">🕮 Преподаватель</option>
                                <option value="admin">🛡 Администратор</option>
                            </select>
                        </div>

                        <button v-if="userSearch || userRoleFilter !== 'all'" class="admin__filter-reset"
                            @click="resetUserFilters">
                            Сбросить
                        </button>
                    </div>

                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Имя</th>
                                    <th>Email</th>
                                    <th>Роль</th>
                                    <th>Преподаватель</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="u in filteredUsers" :key="u.id"
                                    :class="{ 'current-user': u.id === currentUser?.id }">
                                    <td>#{{ u.id }}</td>
                                    <td>{{ u.first_name }} {{ u.last_name }}</td>
                                    <td>{{ u.email }}</td>
                                    <td>
                                        <span class="badge" :class="`badge--${u.role}`">{{ u.role }}</span>
                                        <span v-if="u.id === currentUser?.id" class="badge badge--current">Вы</span>
                                    </td>
                                    <td>{{ u.is_teacher ? '✅' : '❌' }}</td>
                                    <td class="actions">
                                        <button class="icon-btn" @click="openUserModal(u)">✏️</button>
                                        <button class="icon-btn danger" @click="deleteUser(u.id)"
                                            :disabled="u.id === currentUser?.id">🗑️</button>
                                    </td>
                                </tr>
                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="7" class="empty-row">Пользователи не найдены</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PUBLISHED COURSES с поиском и фильтрами -->
                <div v-if="activeTab === 'published'">
                    <!-- 🔹 Панель поиска и фильтров для курсов -->
                    <div class="admin__filters">
                        <div class="admin__search">
                            <input v-model="courseSearch" type="text"
                                placeholder="Поиск по названию, автору или описанию..."
                                class="admin__search-input" />
                            <button v-if="courseSearch" class="admin__search-clear"
                                @click="courseSearch = ''">✕</button>
                        </div>

                        <div class="admin__filter-group">
                            <label class="admin__filter-label">Уровень:</label>
                            <select v-model="courseLevelFilter" class="admin__filter-select">
                                <option value="all">Все уровни</option>
                                <option value="beginner">Начальный</option>
                                <option value="intermediate">Средний</option>
                                <option value="advanced">Продвинутый</option>
                            </select>
                        </div>

                        <button v-if="courseSearch || courseLevelFilter !== 'all'" class="admin__filter-reset"
                            @click="resetCourseFilters">
                            Сбросить
                        </button>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Автор</th>
                                    <th>Уровень</th>
                                    <th>Цена</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in filteredCourses" :key="c.id">
                                    <td>#{{ c.id }}</td>
                                    <td class="title">{{ c.title }}</td>
                                    <td>{{ c.teacher_name }}</td>
                                    <td><span class="badge badge--level">{{ getLevelText(c.level) }}</span></td>
                                    <td>{{ isFree(c.price) ? 'Бесплатно' : `${parseFloat(c.price).toFixed(2)} ₽` }}</td>
                                    <td class="actions">
                                        <button class="icon-btn" @click="openCourseModal(c)">✏️</button>
                                        <button class="icon-btn danger" @click="deleteCourse(c.id)">🗑️</button>
                                    </td>
                                </tr>
                                <tr v-if="filteredCourses.length === 0">
                                    <td colspan="6" class="empty-row">Курсы не найдены</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PENDING / MODERATION -->
                <div v-if="activeTab === 'pending'" class="moderation-grid">
                    <div v-if="pendingCourses.length === 0" class="empty-state">
                        <span class="empty-icon">✨</span>
                        <p>Все курсы проверены. Ожидайте новых заявок.</p>
                    </div>
                    <div v-for="c in pendingCourses" :key="c.id" class="mod-card" @click="openCourse(c.id)">
                        <div class="mod-card__header">
                            <h3 class="mod-card__title">{{ c.title }}</h3>
                            <span class="mod-card__author">by {{ c.teacher_name }}</span>
                        </div>
                        <p class="mod-card__desc">{{ c.description || 'Без описания' }}</p>
                        <div class="mod-card__meta">
                            <span>💰 {{ isFree(c.price) ? 'Бесплатно' : `${parseFloat(c.price).toFixed(2)} ₽` }}</span>
                            <span>📊 {{ getLevelText(c.level) }}</span>
                            <span>📅 {{ new Date(c.created_at).toLocaleDateString() }}</span>
                        </div>
                        <div class="mod-card__actions" @click.stop>
                            <button class="mod-btn approve" @click="moderateCourse(c.id, 'published')">✓
                                Опубликовать</button>
                            <button class="mod-btn reject" @click="moderateCourse(c.id, 'draft')">✕ Отклонить</button>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <!-- USER MODAL -->
        <Transition name="modal">
            <div v-if="showUserModal" class="modal-backdrop" @click.self="showUserModal = false">
                <div class="modal-window">
                    <h3 class="modal-title">{{ editingUser ? 'Редактировать пользователя' : 'Создать пользователя' }}
                    </h3>
                    <div class="modal-content">
                        <label class="form-label">
                            <span>Имя *</span>
                            <input v-model="userForm.first_name" placeholder="Введите имя пользователя"
                                class="modal-input" />
                        </label>
                        <label class="form-label">
                            <span>Фамилия *</span>
                            <input v-model="userForm.last_name" placeholder="Введите фамилию пользователя"
                                class="modal-input" />
                        </label>
                        <label class="form-label">
                            <span>Email *</span>
                            <input v-model="userForm.email" type="email" placeholder="example@teacherpro.com"
                                class="modal-input" />
                        </label>
                        <label v-if="!editingUser" class="form-label">
                            <span>Пароль *</span>
                            <input v-model="userForm.password" type="password" placeholder="Минимум 6 символов"
                                class="modal-input" />
                        </label>
                        <label class="form-label">
                            <span>Роль</span>
                            <select v-model="userForm.role" class="modal-select">
                                <option value="user">👤 Обычный пользователь</option>
                                <option value="teacher">🕮 Преподаватель</option>
                                <option value="admin">🛡 Администратор</option>
                            </select>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" v-model="userForm.is_teacher" />
                            <span>✅ Пользователь является преподавателем (может создавать курсы)</span>
                        </label>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn ghost" @click="showUserModal = false">Отмена</button>
                        <button class="modal-btn primary" @click="saveUser">Сохранить</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- COURSE MODAL -->
        <Transition name="modal">
            <div v-if="showCourseModal" class="modal-backdrop" @click.self="showCourseModal = false">
                <div class="modal-window">
                    <h3 class="modal-title">{{ editingCourse ? 'Редактировать курс' : 'Создать курс' }}</h3>
                    <div class="modal-content">
                        <label class="form-label">
                            <span>Название курса *</span>
                            <input v-model="courseForm.title" placeholder="Например: Поколение Python: профи + OOP"
                                class="modal-input" />
                        </label>
                        <label class="form-label">
                            <span>Краткое описание</span>
                            <textarea v-model="courseForm.description" placeholder="О чём этот курс? (2-3 предложения)"
                                rows="3" class="modal-textarea"></textarea>
                        </label>
                        <label class="form-label">
                            <span>Автор курса</span>
                            <select v-model="courseForm.teacher_id" class="modal-select" :disabled="!!editingCourse">
                                <option v-for="t in teachersList" :key="t.id" :value="t.id">{{ t.first_name }} {{
                                    t.last_name }}
                                </option>
                            </select>
                            <small v-if="editingCourse" class="form-hint">⚠️ Нельзя изменить автора после
                                создания</small>
                        </label>
                        <label class="form-label">
                            <span>Статус</span>
                            <select v-model="courseForm.status" class="modal-select">
                                <option value="draft">📝 Черновик (виден только автору)</option>
                                <option value="published">✅ Опубликован (виден всем в каталоге)</option>
                            </select>
                        </label>
                        <div class="form-row">
                            <label class="form-label flex-1">
                                <span>Цена (₽)</span>
                                <input v-model.number="courseForm.price" type="number" step="0.01" min="0"
                                    placeholder="0.00 для бесплатного" class="modal-input" />
                            </label>
                            <label class="form-label flex-1">
                                <span>Уровень сложности</span>
                                <select v-model="courseForm.level" class="modal-select">
                                    <option value="beginner">🌱 Начальный</option>
                                    <option value="intermediate">🚀 Средний</option>
                                    <option value="advanced">🔥 Продвинутый</option>
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn ghost" @click="showCourseModal = false">Отмена</button>
                        <button class="modal-btn primary" @click="saveCourse">Сохранить</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* === Базовые стили === */
.admin {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: calc(100vh - 64px);
    background: #fff;
}

.admin__sidebar {
    background: #fff;
    border-right: 1px solid #e2e8f0;
    padding: 24px 0;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 64px;
    height: calc(100vh - 64px);
}

.sidebar__banner {
    margin: 0 16px 24px;
    padding: 16px;
    background: #000;
    border-radius: 10px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
}

.sidebar__nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0 8px;
}

.sidebar__link {
    background: transparent;
    border: none;
    padding: 12px 16px;
    text-align: left;
    font-size: 15px;
    color: #4a5568;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
    display: flex;
    justify-content: space-between;
}

.sidebar__link:hover {
    background: #f7fafc;
    color: #000;
}

.sidebar__link.active {
    background: #edf2f7;
    color: #000;
    font-weight: 700;
}

.count {
    background: #e2e8f0;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
}

.admin__main {
    padding: 40px;
}

.admin__title {
    font-size: 32px;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 32px;
}

/* 🔹 Панель фильтров */
.admin__filters {
    display: flex;
    gap: 16px;
    align-items: center;
    margin-bottom: 24px;
    padding: 16px;
    background: #f7fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.admin__search {
    flex: 1;
    min-width: 250px;
    position: relative;
    display: flex;
    align-items: center;
}

.admin__search-input {
    width: 100%;
    padding: 10px 14px 10px 36px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
}

.admin__search-input:focus {
    outline: none;
    border-color: #000;
}

.admin__search::before {
    content: '🔍';
    position: absolute;
    left: 12px;
    font-size: 14px;
    opacity: 0.5;
}

.admin__search-clear {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    font-size: 16px;
    cursor: pointer;
    color: #718096;
    padding: 4px 8px;
    border-radius: 4px;
}

.admin__search-clear:hover {
    background: #e2e8f0;
    color: #000;
}

.admin__filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin__filter-label {
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
}

.admin__filter-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    cursor: pointer;
}

.admin__filter-select:focus {
    outline: none;
    border-color: #000;
}

.admin__filter-reset {
    padding: 8px 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s;
}

.admin__filter-reset:hover {
    background: #f7fafc;
    border-color: #000;
    color: #000;
}

/* Таблицы */
.data-table,
.table-wrap {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #f7fafc;
}

th,
td {
    padding: 16px 24px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

th {
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    font-size: 12px;
}

tbody tr:hover {
    background: #fafafa;
}

tbody tr.current-user {
    background: #fffbeb;
    border-left: 3px solid #f6ad55;
}

.title {
    font-weight: 600;
    color: #000;
}

.empty-row {
    text-align: center;
    color: #718096;
    padding: 32px !important;
}

.actions {
    display: flex;
    gap: 8px;
}

.icon-btn {
    background: none;
    border: 1px solid #e2e8f0;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.icon-btn:hover {
    background: #f7fafc;
    border-color: #000;
}

.icon-btn.danger:hover {
    background: #fff5f5;
    border-color: #e53e3e;
}

.icon-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Бейджи */
.badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    margin-right: 4px;
}

.badge--admin {
    background: #000;
    color: #fff;
}

.badge--teacher {
    background: #e2e8f0;
    color: #2d3748;
}

.badge--user {
    background: #edf2f7;
    color: #2d3748;
}

.badge--level {
    background: #f7fafc;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.badge--current {
    background: #f6ad55;
    color: #fff;
}

/* 🔹 Выпадающий список для смены роли */
.role-select {
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
    background: #fff;
    cursor: pointer;
    min-width: 120px;
}

.role-select:focus {
    outline: none;
    border-color: #000;
}

.role-select-disabled {
    color: #718096;
    font-size: 13px;
    font-style: italic;
}

/* Moderation Grid */
.moderation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
}

.mod-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 12px;
    cursor: pointer;
}

.mod-card:hover {
    border-color: #000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.mod-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.mod-card__title {
    font-size: 18px;
    font-weight: 700;
    color: #1a202c;
    margin: 0;
}

.mod-card__author {
    font-size: 13px;
    color: #718096;
    white-space: nowrap;
}

.mod-card__desc {
    color: #4a5568;
    font-size: 14px;
    line-height: 1.5;
    margin: 0;
    flex: 1;
}

.mod-card__meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: #718096;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
}

.mod-card__actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.mod-btn {
    flex: 1;
    padding: 10px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid;
    font-size: 14px;
}

.mod-btn.approve {
    background: #000;
    color: #fff;
    border-color: #000;
}

.mod-btn.approve:hover {
    background: #2d2d2d;
}

.mod-btn.reject {
    background: #fff;
    color: #000;
    border-color: #000;
}

.mod-btn.reject:hover {
    background: #f7fafc;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #718096;
}

.empty-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 16px;
    opacity: 0.5;
}

.loading {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 60px;
    color: #718096;
}

.loader {
    width: 24px;
    height: 24px;
    border: 3px solid #e2e8f0;
    border-top-color: #000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Модальные окна */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3000;
    padding: 20px;
}

.modal-window {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    margin: 0;
}

.modal-content {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
}

.form-label>span {
    font-size: 13px;
    font-weight: 600;
    color: #1a202c;
}

.form-hint {
    font-size: 11px;
    color: #718096;
    margin-top: 4px;
    font-style: italic;
}

.modal-input,
.modal-select,
.modal-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    background: #fff;
}

.modal-input:focus,
.modal-select:focus {
    outline: none;
    border-color: #000;
}

.modal-textarea {
    resize: vertical;
    min-height: 80px;
}

.modal-select:disabled {
    background: #f7fafc;
    color: #718096;
    cursor: not-allowed;
}

.form-row {
    display: flex;
    gap: 16px;
    width: 100%;
}

.form-row .form-label {
    flex: 1;
}

.flex-1 {
    flex: 1;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #4a5568;
    cursor: pointer;
    padding: 8px 0;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.modal-actions {
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: #fff;
}

.modal-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.modal-btn.primary {
    background: #000;
    color: #fff;
}

.modal-btn.primary:hover {
    background: #2d2d2d;
}

.modal-btn.ghost {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #4a5568;
}

.modal-btn.ghost:hover {
    background: #f7fafc;
}

.modal-enter-active,
.modal-leave-active {
    transition: all 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .modal-window {
    transform: scale(0.95);
}

/* Адаптивность */
@media (max-width: 968px) {
    .admin {
        grid-template-columns: 1fr;
    }

    .admin__sidebar {
        display: none;
    }

    .admin__main {
        padding: 24px;
    }

    .admin__filters {
        flex-direction: column;
        align-items: stretch;
    }

    .admin__search {
        min-width: 100%;
    }

    .form-row {
        flex-direction: column;
        gap: 16px;
    }
}
</style>