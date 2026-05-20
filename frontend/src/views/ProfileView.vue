<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { removeAuth } from '../auth'
import { useToast } from '../composables/useToast'
import CourseCard from '../components/ui/CourseCard.vue'
import { api } from '../api'

const router = useRouter()
const { currentUser, userFullName, userInitial } = useAuth()
const { pushStatus } = useToast()

const wishlistCourses = ref([])
const isLoadingWishlist = ref(false)
// Пагинация
const currentPage = ref(1)
const itemsPerPage = 4


const certificates = ref([])
const isLoadingCerts = ref(false)

const coursesCompleted = computed(() => certificates.value.length)

async function loadCertificates() {
    if (!currentUser.value) return
    isLoadingCerts.value = true
    try {
        const res = await api.getUserCertificates()
        certificates.value = res.certificates || []
    } catch (err) {
        console.error('Failed to load certificates:', err)
    } finally {
        isLoadingCerts.value = false
    }
}


// Вычисляемые курсы для текущей страницы
const paginatedWishlist = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage
    const end = start + itemsPerPage
    return wishlistCourses.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(wishlistCourses.value.length / itemsPerPage))

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return
    currentPage.value = page
    // Прокрутка к началу секции
    document.querySelector('.profile__wishlist-section')?.scrollIntoView({ behavior: 'smooth' })
}

function nextPage() {
    if (currentPage.value < totalPages.value) {
        currentPage.value++
        document.querySelector('.profile__wishlist-section')?.scrollIntoView({ behavior: 'smooth' })
    }
}

function prevPage() {
    if (currentPage.value > 1) {
        currentPage.value--
        document.querySelector('.profile__wishlist-section')?.scrollIntoView({ behavior: 'smooth' })
    }
}

// Сброс на первую страницу при обновлении списка
watch(() => wishlistCourses.value.length, () => {
    currentPage.value = 1
})

function openCourse(id) {
    router.push(`/course/${id}`)
}

const profileForm = ref({
    first_name: '',
    last_name: '',
    email: '',
    bio: '',
    about: '',
    is_private: false,
    is_teacher: false,
})

const isProfileLoading = ref(false)

async function loadProfile() {
    if (!currentUser.value) return

    isProfileLoading.value = true
    try {
        profileForm.value = {
            first_name: currentUser.value.first_name || '',
            last_name: currentUser.value.last_name || '',
            email: currentUser.value.email || '',
            bio: currentUser.value.bio || '',
            about: currentUser.value.about || '',
            is_private: !!currentUser.value.is_private,
            is_teacher: !!currentUser.value.is_teacher,
        }
    } catch (err) {
        console.error('Failed to load profile:', err)
        pushStatus('Не удалось загрузить профиль', 'error')
    } finally {
        isProfileLoading.value = false
    }
}

function handleLogout() {
    removeAuth()
    pushStatus('Вы вышли из аккаунта', 'info')
    setTimeout(() => {
        window.location.reload()
    }, 500)
}

onMounted(() => {
    loadProfile()
    loadCertificates()
})
</script>

<template>
    <div class="profile">
        <div class="profile__container">
            <!-- Sidebar -->
            <aside class="profile__sidebar">
                <div class="profile__avatar">
                    {{ userInitial || 'U' }}
                </div>

                <h2 class="profile__name">{{ userFullName }}</h2>

                <div class="profile__email">
                    <span class="profile__icon">✉</span>
                    {{ profileForm.email }}
                </div>

                <div v-if="profileForm.is_teacher" class="profile__role">
                    <span class="profile__icon">🕮</span>
                    Преподаватель
                </div>

                <nav class="profile__nav">
                    <a href="#" class="profile__nav-link profile__nav-link--active">
                        Профиль
                    </a>
                    <a href="/profile/edit" class="profile__nav-link">
                        Редактировать профиль
                    </a>
                    <a href="#" class="profile__nav-link profile__nav-link--logout" @click.prevent="handleLogout">
                        Выйти
                    </a>
                </nav>

                <div class="profile__joined">
                    Присоединился {{ new Date().toLocaleDateString('ru-RU') }}
                </div>
            </aside>

            <!-- Main -->
            <main class="profile__main">
                <h1 class="profile__title">{{ userFullName }}</h1>

                <!-- Stats -->
                <div class="profile__stats">
                    <div class="profile__stat">
                        <div class="profile__stat-value">{{ coursesCompleted }}</div> <!-- 🔹 Обновлено -->
                        <div class="profile__stat-label">курсов пройдено</div>
                    </div>
                    <div class="profile__stat">
                        <div class="profile__stat-value">{{ certificates.length }}</div> <!-- 🔹 Обновлено -->
                        <div class="profile__stat-label">сертификатов</div>
                    </div>
                </div>

                <!-- Bio -->
                <section v-if="profileForm.bio" class="profile__section">
                    <h2 class="profile__section-title">О себе</h2>
                    <p class="profile__bio">{{ profileForm.bio }}</p>
                </section>

                <!-- About -->
                <section v-if="profileForm.about" class="profile__section">
                    <h2 class="profile__section-title">Подробная информация</h2>
                    <p class="profile__about">{{ profileForm.about }}</p>
                </section>

                <!-- Certificates -->
                <section class="profile__section">
                    <h2 class="profile__section-title">Сертификаты</h2>

                    <div v-if="isLoadingCerts" class="profile__loading">
                        <div class="profile__loader"></div>
                        <span>Загрузка сертификатов...</span>
                    </div>

                    <div v-else-if="certificates.length === 0" class="profile__certificates">
                        <p class="profile__empty">Пока нет сертификатов. Пройдите курс до конца!</p>
                    </div>

                    <div v-else class="certificates-grid">
                        <div v-for="cert in certificates" :key="cert.id" class="certificate-card">
                            <div class="certificate-card__header">
                                <span class="certificate-card__icon">🏆</span>
                                <span class="certificate-card__code">{{ cert.certificate_code }}</span>
                            </div>
                            <h3 class="certificate-card__title">{{ cert.course_title }}</h3>
                            <p class="certificate-card__date">Выдан: {{ new
                                Date(cert.issued_at).toLocaleDateString('ru-RU') }}</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
.profile {
    min-height: 100vh;
    background: #f7fafc;
    padding: 40px 0;
}

.profile__container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 48px;
}

/* Sidebar */
.profile__sidebar {
    background: white;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    height: fit-content;
    border: 1px solid #e2e8f0;
}

.profile__avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #000000;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: 700;
    margin: 0 auto 24px;
}

.profile__name {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 12px;
    color: #1a202c;
}

.profile__email,
.profile__role {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 14px;
    color: #4a5568;
    margin-bottom: 8px;
}

.profile__icon {
    font-size: 16px;
}

.profile__joined {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
    font-size: 13px;
    color: #718096;
}

.profile__nav {
    margin-top: 32px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.profile__nav-link {
    padding: 12px 20px;
    border-radius: 8px;
    color: #4a5568;
    text-decoration: none;
    transition: all 0.2s;
    display: block;
    font-weight: 500;
}

.profile__nav-link:hover {
    background: #f7fafc;
    color: #2d3748;
}

.profile__nav-link--active {
    background: #000000;
    color: #ffffff;
}

.profile__nav-link--logout {
    color: #e53e3e;
}

.profile__nav-link--logout:hover {
    background: #fff5f5;
}

/* Main */
.profile__main {
    background: white;
    border-radius: 16px;
    padding: 40px;
    border: 1px solid #e2e8f0;
}

.profile__title {
    font-size: 36px;
    font-weight: 800;
    margin: 0 0 32px;
    color: #1a202c;
}

.profile__stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 48px;
}

.profile__stat {
    background: #f7fafc;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    border: 1px solid #e2e8f0;
}

.profile__stat-value {
    font-size: 40px;
    font-weight: 800;
    color: #000000;
    margin-bottom: 8px;
}

.profile__stat-label {
    font-size: 14px;
    color: #718096;
}

.profile__section {
    margin-top: 48px;
}

.profile__section-title {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 20px;
    color: #1a202c;
    padding-bottom: 12px;
    border-bottom: 2px solid #000000;
}

.profile__bio,
.profile__about {
    color: #4a5568;
    line-height: 1.7;
    font-size: 15px;
}

.profile__certificates {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 48px;
    text-align: center;
}

.profile__empty {
    color: #718096;
    font-size: 16px;
}

@media (max-width: 968px) {
    .profile__container {
        grid-template-columns: 1fr;
    }

    .profile__sidebar {
        order: -1;
    }
}

.profile__wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.profile__loading {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 24px;
    color: #718096;
}

.profile__loader {
    width: 20px;
    height: 20px;
    border: 2px solid #e2e8f0;
    border-top-color: #000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.profile__empty {
    padding: 32px;
    text-align: center;
    background: #f7fafc;
    border-radius: 12px;
    border: 1px dashed #cbd5e0;
}

.profile__empty p {
    color: #718096;
    margin: 0 0 16px;
}

.profile__btn-link {
    background: none;
    border: none;
    color: #000;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
    transition: opacity 0.2s;
}

.profile__btn-link:hover {
    opacity: 0.7;
}

.profile__wishlist-section {
    scroll-margin-top: 100px;
}

.profile__wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.certificates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.certificate-card {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.2s;
}

.certificate-card:hover {
  border-color: #000000;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.certificate-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.certificate-card__icon {
  font-size: 28px;
}

.certificate-card__code {
  background: #000000;
  color: #ffffff;
  padding: 4px 10px;
  border-radius: 6px;
  font-family: monospace;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 1px;
}

.certificate-card__title {
  font-size: 16px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 8px;
  line-height: 1.3;
}

.certificate-card__date {
  font-size: 13px;
  color: #718096;
  margin: 0;
}

/* Пагинация */
.profile__pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 32px;
    padding: 20px 0;
}

.profile__pagination-btn {
    padding: 10px 20px;
    background: #fff;
    border: 2px solid #000;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: #000;
}

.profile__pagination-btn:hover:not(:disabled) {
    background: #000;
    color: #fff;
}

.profile__pagination-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.profile__pagination-numbers {
    display: flex;
    gap: 8px;
}

.profile__pagination-page {
    width: 40px;
    height: 40px;
    border: 2px solid #e2e8f0;
    background: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile__pagination-page:hover {
    border-color: #000;
    background: #f7fafc;
}

.profile__pagination-page.active {
    background: #000;
    color: #fff;
    border-color: #000;
}

.profile__wishlist-info {
    text-align: center;
    color: #718096;
    font-size: 14px;
    margin-top: 16px;
}

/* Адаптивность пагинации */
@media (max-width: 640px) {
    .profile__pagination {
        flex-wrap: wrap;
    }

    .profile__pagination-numbers {
        order: 3;
        width: 100%;
        justify-content: center;
        margin-top: 8px;
    }

    .profile__pagination-btn {
        padding: 8px 16px;
        font-size: 13px;
    }
}
</style>