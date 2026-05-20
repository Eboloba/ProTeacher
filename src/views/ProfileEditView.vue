<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { useToast } from '../composables/useToast'
import { api } from '../api'
import { storeAuth, loadToken, loadUser } from '../auth'

const router = useRouter()
const { currentUser, updateUser } = useAuth()
const { pushStatus } = useToast()

const profileForm = ref({
    first_name: '',
    last_name: '',
    email: '',
    bio: '',
    about: '',
    is_private: false,
    is_teacher: false,
})

const passwordForm = ref({
    current_password: '',
    new_password: '',
    confirm_password: '',
})

const showPasswordSection = ref(false)
const isProfileSaving = ref(false)

async function loadProfile() {
    if (!currentUser.value) return

    try {
        profileForm.value = {
            first_name: currentUser.value.first_name || '',
            last_name: currentUser.value.last_name || '',
            email: currentUser.value.email || '',
            bio: currentUser.value.bio || '',
            about: currentUser.value.about || '',
            is_private: !!currentUser.value.is_private,
            is_teacher: currentUser.value.is_teacher,
        }

        console.log(profileForm.value);
    } catch (err) {
        console.error('Failed to load profile:', err)
    }
}

async function saveProfile() {
    if (!currentUser.value) {
        pushStatus('Войдите для сохранения профиля', 'error')
        return
    }

    isProfileSaving.value = true
    pushStatus('Сохранение...', 'info')

    try {
        const newRole = profileForm.value.is_teacher ? 'teacher' : 'user'

        const payload = {
            first_name: profileForm.value.first_name,
            last_name: profileForm.value.last_name,
            email: profileForm.value.email,
            bio: profileForm.value.bio,
            about: profileForm.value.about,
            is_private: profileForm.value.is_private,
            role: newRole,
        }

        if (showPasswordSection.value && passwordForm.value.new_password) {
            if (passwordForm.value.new_password.length < 6) {
                throw new Error('Новый пароль должен содержать минимум 6 символов')
            }
            if (passwordForm.value.new_password !== passwordForm.value.confirm_password) {
                throw new Error('Пароли не совпадают')
            }

            payload.current_password = passwordForm.value.current_password
            payload.new_password = passwordForm.value.new_password
        }

        const res = await api.updateProfile(payload)

        if (res.user) {
            storeAuth(loadToken(), res.user)
            updateUser(loadUser())

            profileForm.value = {
                first_name: currentUser.value.first_name || '',
                last_name: currentUser.value.last_name || '',
                email: currentUser.value.email || '',
                bio: currentUser.value.bio || '',
                about: currentUser.value.about || '',
                is_private: currentUser.value.is_private || false,
                is_teacher: currentUser.value.role === 'teacher',
            }

            passwordForm.value = {
                current_password: '',
                new_password: '',
                confirm_password: '',
            }
            showPasswordSection.value = false

            pushStatus('Профиль успешно сохранён', 'success')

            if (newRole === 'teacher' && currentUser.value.role !== 'teacher') {
                pushStatus('🎉 Теперь вы можете создавать курсы!', 'success', 5000)
            }

            setTimeout(() => window.location.reload(), 600)
        }
    } catch (err) {
        if (err.message?.includes('Неверный текущий пароль')) {
            pushStatus('❌ Неверный текущий пароль', 'error')
            passwordForm.value.current_password = ''
            return
        }

        if (err.message?.includes('Session expired') || err.message?.includes('Unauthorized')) {
            pushStatus('⚠️ Сессия истекла. Войдите снова', 'error')
            setTimeout(() => router.push('/auth/login'), 1500)
            return
        }

        pushStatus('Ошибка: ' + (err.message || 'Не удалось сохранить'), 'error')
    }
     finally {
        isProfileSaving.value = false
     }
}


onMounted(() => {
    loadProfile()
})
</script>

<template>
    <div class="profile-edit">
        <div class="profile-edit__container">
            <!-- Sidebar -->
            <aside class="profile-edit__sidebar">
                <nav class="profile-edit__nav">
                    <a href="/profile" class="profile-edit__nav-link">
                        ← Назад к профилю
                    </a>
                    <a href="/profile/edit" class="profile-edit__nav-link profile-edit__nav-link--active">
                        Редактировать профиль
                    </a>
                </nav>
            </aside>

            <!-- Form -->
            <main class="profile-edit__main">
                <h1 class="profile-edit__title">Редактирование профиля</h1>

                <form @submit.prevent="saveProfile" class="profile-edit__form">
                    <div class="profile-edit__row">
                        <div class="profile-edit__group">
                            <label class="profile-edit__label">Ваше имя *</label>
                            <input v-model="profileForm.first_name" type="text" required placeholder="Имя"
                                class="profile-edit__input" />
                        </div>

                        <div class="profile-edit__group">
                            <label class="profile-edit__label">Фамилия *</label>
                            <input v-model="profileForm.last_name" type="text" required placeholder="Фамилия"
                                class="profile-edit__input" />
                            <small class="profile-edit__hint">Ваше официальное имя, используемое в сертификатах.</small>
                        </div>
                    </div>

                    <div class="profile-edit__group">
                        <label class="profile-edit__label">Краткая биография (до 255 символов)</label>
                        <textarea v-model="profileForm.bio" maxlength="255" rows="2" placeholder="Расскажите о себе..."
                            class="profile-edit__textarea"></textarea>
                        <small class="profile-edit__hint">{{ profileForm.bio.length }}/255</small>
                    </div>


                    <div class="profile-edit__group">
                        <label class="profile-edit__label">Обо мне</label>
                        <textarea v-model="profileForm.about" rows="5" placeholder="Подробная информация о себе..."
                            class="profile-edit__textarea"></textarea>
                    </div>

                    <div class="profile-edit__group">
                        <label class="profile-edit__label">Email</label>
                        <input v-model="profileForm.email" type="email" required placeholder="example@teacherpro.com"
                            class="profile-edit__input" />
                    </div>

                    <div class="profile-edit__password-section">
                        <button type="button" class="profile-edit__toggle-password"
                            @click="showPasswordSection = !showPasswordSection">
                            {{ showPasswordSection ? '✕ Отменить смену пароля' : 'Сменить пароль' }}
                        </button>

                        <Transition name="fade">
                            <div v-if="showPasswordSection" class="profile-edit__password-form">
                                <div class="profile-edit__group">
                                    <label class="profile-edit__label">Текущий пароль *</label>
                                    <input v-model="passwordForm.current_password" type="password"
                                        placeholder="••••••••" class="profile-edit__input" required />
                                </div>

                                <div class="profile-edit__group">
                                    <label class="profile-edit__label">Новый пароль *</label>
                                    <input v-model="passwordForm.new_password" type="password"
                                        placeholder="Минимум 6 символов" class="profile-edit__input" minlength="6" />
                                </div>

                                <div class="profile-edit__group">
                                    <label class="profile-edit__label">Подтвердите новый пароль *</label>
                                    <input v-model="passwordForm.confirm_password" type="password"
                                        placeholder="Повторите пароль" class="profile-edit__input" />
                                </div>

                            </div>
                        </Transition>
                    </div>

                    <div class="profile-edit__group profile-edit__checkbox">
                        <label>
                            <input type="checkbox" v-model="profileForm.is_teacher" />
                            <span>Я являюсь преподавателем</span>
                        </label>
                    </div>

                    <div class="profile-edit__actions">
                        <button type="button" class="profile-edit__btn profile-edit__btn--ghost"
                            @click="router.push('/profile')">
                            Отмена
                        </button>
                        <button type="submit" class="profile-edit__btn profile-edit__btn--primary"
                            :disabled="isProfileSaving">
                            {{ isProfileSaving ? 'Сохранение...' : 'Сохранить изменения' }}
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
</template>

<style scoped>
.profile-edit {
    min-height: 100vh;
    background: #f7fafc;
    padding: 40px 0;
}

.profile-edit__container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 48px;
}

.profile-edit__sidebar {
    height: fit-content;
}

.profile-edit__nav {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.profile-edit__nav-link {
    padding: 12px 20px;
    border-radius: 8px;
    color: #4a5568;
    text-decoration: none;
    transition: all 0.2s;
    display: block;
    font-weight: 500;
}

.profile-edit__nav-link:hover {
    background: white;
    color: #2d3748;
}

.profile-edit__nav-link--active {
    background: #000000;
    color: #ffffff;
}

.profile-edit__main {
    background: white;
    border-radius: 16px;
    padding: 40px;
    border: 1px solid #e2e8f0;
}

.profile-edit__title {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 40px;
    color: #1a202c;
}

.profile-edit__form {
    max-width: 800px;
}

.profile-edit__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.profile-edit__group {
    margin-bottom: 28px;
}

.profile-edit__password-section {
    margin: 24px 0;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
}

.profile-edit__toggle-password {
    background: none;
    border: none;
    color: #4299e1;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    margin-bottom: 16px;
    transition: color 0.2s;
}

.profile-edit__toggle-password:hover {
    color: #2b6cb0;
    text-decoration: underline;
}

.profile-edit__password-form {
    display: grid;
    gap: 16px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease, max-height 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    max-height: 0;
    overflow: hidden;
}

.profile-edit__label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
}

.profile-edit__input,
.profile-edit__textarea {
    width: 100%;
    padding: 14px 18px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    transition: border-color 0.2s;
    font-family: inherit;
}

.profile-edit__input:focus,
.profile-edit__textarea:focus {
    outline: none;
    border-color: #000000;
}

.profile-edit__hint {
    display: block;
    margin-top: 8px;
    font-size: 13px;
    color: #718096;
}

.profile-edit__checkbox label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    font-weight: 500;
    color: #2d3748;
}

.profile-edit__checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.profile-edit__actions {
    margin-top: 40px;
    padding-top: 32px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 16px;
    justify-content: flex-end;
}

.profile-edit__btn {
    padding: 14px 32px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.profile-edit__btn--primary {
    background: #000000;
    color: white;
}

.profile-edit__btn--primary:hover:not(:disabled) {
    background: #2d2d2d;
}

.profile-edit__btn--primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.profile-edit__btn--ghost {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #4a5568;
}

.profile-edit__btn--ghost:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

@media (max-width: 968px) {
    .profile-edit__container {
        grid-template-columns: 1fr;
    }

    .profile-edit__row {
        grid-template-columns: 1fr;
    }
}
</style>