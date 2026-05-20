<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../../api'
import { storeAuth } from '../../auth'
import { useAuth } from '../../composables/useAuth'
import { useToast } from '../../composables/useToast'
import Modal from '../ui/Modal.vue'

const route = useRoute()
const router = useRouter()
const { updateUser } = useAuth()
const { pushStatus } = useToast()

const props = defineProps({
    modelValue: Boolean
})

const emit = defineEmits(['update:modelValue', 'close'])

const authTab = ref('login')
const isSubmitting = ref(false)
const loginForm = ref({ email: '', password: '' })
const registerForm = ref({ email: '', password: '', agree: false })

// 🔹 ИСПРАВЛЕНИЕ: Создаем computed-обертку для безопасного v-model
const showModal = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

// 🔹 Следим за URL. Если появляется ?auth=..., открываем окно
watch(() => route.query.auth, (newVal) => {
    if (newVal === 'login' || newVal === 'register') {
        authTab.value = newVal
        showModal.value = true
    }
}, { immediate: true })

async function handleLogin() {
    if (!loginForm.value.email || !loginForm.value.password) {
        pushStatus('Введите email и пароль', 'error')
        return
    }

    isSubmitting.value = true
    try {
        const res = await api.login(loginForm.value)
        storeAuth(res.token, res.user)
        updateUser(res.user)

        closeModal()
        setTimeout(() => window.location.reload(), 500)
        pushStatus(`Добро пожаловать!`, 'success')
        loginForm.value = { email: '', password: '' }
    } catch (err) {
        pushStatus(err.message || 'Ошибка входа', 'error')
    } finally {
        isSubmitting.value = false
    }
}

async function handleRegister() {
    if (!registerForm.value.agree) {
        pushStatus('Необходимо согласиться с условиями', 'error')
        return
    }
    isSubmitting.value = true
    try {
        await api.register(registerForm.value)
        pushStatus('Успешно! Теперь войдите.', 'success')
        authTab.value = 'login'
        registerForm.value = { email: '', password: '', agree: false }
    } catch (err) {
        pushStatus(err.message || 'Ошибка', 'error')
    } finally {
        isSubmitting.value = false
    }
}

function closeModal() {
    emit('update:modelValue', false)
    if (route.query.auth) {
        router.replace({ query: { ...route.query, auth: undefined } })
    }
}

function onClose() {
    closeModal()
}
</script>

<template>
    <!-- 🔹 Теперь v-model привязан к computed-свойству, а не к пропсу -->
    <Modal v-model="showModal" @close="onClose">
        <div class="auth-modal">
            <div class="auth-modal__header">
                <div class="auth-modal__tabs">
                    <button :class="['auth-modal__tab', { active: authTab === 'login' }]" @click="authTab = 'login'">
                        Войти
                    </button>
                    <button :class="['auth-modal__tab', { active: authTab === 'register' }]"
                        @click="authTab = 'register'">
                        Регистрация
                    </button>
                </div>
                <button class="auth-modal__close" @click="closeModal">×</button>
            </div>

            <!-- Форма входа -->
            <div v-if="authTab === 'login'" class="auth-modal__content">
                <input v-model="loginForm.email" placeholder="E-mail" type="email" class="auth-modal__input" />
                <input v-model="loginForm.password" type="password" placeholder="Пароль" @keyup.enter="handleLogin"
                    class="auth-modal__input" />
                <button class="auth-modal__btn auth-modal__btn--primary" @click="handleLogin" :disabled="isSubmitting">
                    {{ isSubmitting ? 'Вход...' : 'Войти' }}
                </button>
                <a href="#" class="auth-modal__link">Напомнить пароль</a>
            </div>

            <!-- Форма регистрации -->
            <div v-else class="auth-modal__content auth-modal__content--register">
                <div class="auth-modal__stack">
                    <input v-model="registerForm.email" placeholder="E-mail" type="email" class="auth-modal__input" />
                    <input v-model="registerForm.password" type="password" placeholder="Пароль"
                        class="auth-modal__input" />
                </div>
                <label class="auth-modal__agreement">
                    <input type="checkbox" v-model="registerForm.agree" />
                    <span>Я соглашаюсь с <a href="#">условиями использования</a></span>
                </label>
                <button class="auth-modal__btn auth-modal__btn--primary" @click="handleRegister"
                    :disabled="isSubmitting">
                    Зарегистрироваться
                </button>
            </div>

            <div class="auth-modal__social">
                <p>Или через соцсети</p>
                <div class="auth-modal__social-row">
                    <button class="auth-modal__social-btn auth-modal__social-btn--vk">VK</button>
                    <button class="auth-modal__social-btn auth-modal__social-btn--g">G</button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.auth-modal {
    padding: 0;
}

.auth-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.auth-modal__tabs {
    display: flex;
    gap: 24px;
}

.auth-modal__tab {
    border: none;
    background: transparent;
    color: #718096;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    padding: 0 0 8px;
    position: relative;
    transition: color 0.2s;
}

.auth-modal__tab:hover {
    color: #2d3748;
}

.auth-modal__tab.active {
    color: #000000;
}

.auth-modal__tab.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: #000000;
}

.auth-modal__close {
    border: none;
    background: transparent;
    font-size: 32px;
    line-height: 1;
    cursor: pointer;
    color: #718096;
}

.auth-modal__content {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.auth-modal__content--register {
    padding-top: 30px;
}

.auth-modal__input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 15px;
}

.auth-modal__input:focus {
    outline: none;
    border-color: #000000;
}

.auth-modal__btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

.auth-modal__btn--primary {
    background: #000000;
    color: #ffffff;
}

.auth-modal__btn--primary:hover:not(:disabled) {
    background: #2d2d2d;
}

.auth-modal__btn--primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.auth-modal__link {
    text-decoration: none;
    color: #4a5568;
    font-size: 13px;
    text-align: center;
    display: block;
    padding: 8px 0;
}

.auth-modal__stack {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.auth-modal__stack .auth-modal__input {
    border: none;
    border-bottom: 1px solid #e2e8f0;
    border-radius: 0;
}

.auth-modal__agreement {
    margin-top: 8px;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    color: #4a5568;
    font-size: 13px;
}

.auth-modal__agreement a {
    color: #000000;
    text-decoration: underline;
}

.auth-modal__social {
    border-top: 1px solid #e2e8f0;
    margin-top: 8px;
    text-align: center;
    padding: 24px;
}

.auth-modal__social p {
    margin: 0 0 16px;
    color: #718096;
    font-size: 14px;
}

.auth-modal__social-row {
    display: flex;
    justify-content: center;
    gap: 12px;
}

.auth-modal__social-btn {
    border: none;
    color: #fff;
    font-weight: 700;
    border-radius: 8px;
    width: 44px;
    height: 44px;
    font-size: 14px;
    cursor: pointer;
}

.auth-modal__social-btn--vk {
    background: #4a76a8;
}

.auth-modal__social-btn--g {
    background: #ea4335;
}
</style>