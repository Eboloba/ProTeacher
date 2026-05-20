<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const emit = defineEmits(['close'])

const leftCatalogItems = [
  "Все направления",
  "Языки программирования",
  "Веб-разработка",
  "Мобильная разработка",
  "Data Science и аналитика",
  "DevOps и системное администрирование",
  "Тестирование и QA",
  "Искусственный интеллект",
  "Кибербезопасность",
  "1С и ERP-системы",
  "GameDev",
  "Soft Skills для IT",
]

const catalogCategoryMap = {
  "Все направления": "all",
  "Языки программирования": "programming",
  "Веб-разработка": "web",
  "Мобильная разработка": "programming",
  "Data Science и аналитика": "data",
  "DevOps и системное администрирование": "devops",
  "Тестирование и QA": "testing",
  "Искусственный интеллект": "ai",
  "Кибербезопасность": "devops",
  "1С и ERP-системы": "programming",
  "GameDev": "programming",
  "Soft Skills для IT": "all",
}

const catalogColumns = [
  {
    title: "Языки и технологии",
    items: ["Python", "JavaScript/TypeScript", "Java", "C/C++", "C#", "Go", "PHP", "SQL"],
  },
  {
    title: "Веб-фронтенд",
    items: ["HTML/CSS", "React", "Vue.js", "Angular", "Next.js", "Webpack", "TypeScript"],
  },
  {
    title: "Веб-бэкенд",
    items: ["Node.js", "Django/FastAPI", "Spring", "ASP.NET", "Laravel", "REST API", "GraphQL"],
  },
  {
    title: "Данные и ML",
    items: ["Pandas/NumPy", "Scikit-learn", "TensorFlow", "PyTorch", "SQL", "Apache Spark"],
  },
  {
    title: "DevOps и инфраструктура",
    items: ["Docker", "Kubernetes", "Linux", "CI/CD", "Terraform", "AWS/GCP", "Nginx"],
  },
  {
    title: "Тестирование и безопасность",
    items: ["Pytest", "Selenium", "Playwright", "Postman", "OWASP", "Пентестинг"],
  },
]

function openCatalogCategory(item) {
  const category = catalogCategoryMap[item] || 'all'
  router.push(`/catalog?category=${category}`)
  emit('close')
}

function close() {
  emit('close')
}
</script>

<template>
  <Transition name="fade">
    <div class="catalog-modal" @click.self="close">
      <div class="catalog-modal__window">
        <!-- Sidebar -->
        <aside class="catalog-modal__sidebar">
          <div class="catalog-modal__sidebar-header">
            <span class="catalog-modal__logo">TP</span>
            <span class="catalog-modal__sidebar-title">Каталог</span>
          </div>
          
          <nav class="catalog-modal__nav">
            <button 
              v-for="(item, index) in leftCatalogItems" 
              :key="item" 
              :class="['catalog-modal__nav-item', { active: index === 0 }]"
              @click="openCatalogCategory(item)"
            >
              <span class="catalog-modal__nav-icon">{{ index + 1 }}</span>
              <span class="catalog-modal__nav-text">{{ item }}</span>
              <span class="catalog-modal__nav-arrow">→</span>
            </button>
          </nav>
          
          <div class="catalog-modal__sidebar-footer">
            <a href="#" class="catalog-modal__companies" @click.prevent>
              <span>💼</span> Компаниям
            </a>
          </div>
        </aside>
        
        <!-- Content -->
        <div class="catalog-modal__content">
          <button class="catalog-modal__close" @click="close">
            <span>✕</span>
          </button>
          
          <header class="catalog-modal__header">
            <h3 class="catalog-modal__title">Информационные технологии</h3>
            <p class="catalog-modal__subtitle">Выберите направление или технологию для поиска курсов</p>
          </header>
          
          <div class="catalog-modal__columns">
            <article v-for="column in catalogColumns" :key="column.title" class="catalog-modal__col">
              <h4 class="catalog-modal__col-title">
                <span class="catalog-modal__col-dot"></span>
                {{ column.title }}
              </h4>
              <ul class="catalog-modal__list">
                <li v-for="item in column.items" :key="item">
                  <button 
                    class="catalog-modal__item"
                    @click="router.push(`/catalog?search=${item}`); close()"
                  >
                    {{ item }}
                  </button>
                </li>
              </ul>
            </article>
          </div>
          
          <footer class="catalog-modal__footer">
            <p>Не нашли нужное? <a href="/catalog" @click.prevent="close">Перейти в полный каталог →</a></p>
          </footer>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* === Modal Backdrop === */
.catalog-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 80px;
  z-index: 1500;
  backdrop-filter: blur(4px);
}

/* === Window === */
.catalog-modal__window {
  width: min(1280px, 96vw);
  background: #ffffff;
  display: grid;
  grid-template-columns: 300px 1fr;
  max-height: calc(100vh - 100px);
  overflow: hidden;
  border-radius: 20px;
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
  animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* === Sidebar === */
.catalog-modal__sidebar {
  background: #000000;
  color: #ffffff;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #1a1a1a;
}

.catalog-modal__sidebar-header {
  padding: 24px 28px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid #1a1a1a;
}

.catalog-modal__logo {
  width: 36px;
  height: 36px;
  background: #ffffff;
  color: #000000;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 14px;
  letter-spacing: -0.5px;
}

.catalog-modal__sidebar-title {
  font-size: 18px;
  font-weight: 700;
}

.catalog-modal__nav {
  flex: 1;
  overflow-y: auto;
  padding: 12px 8px;
}

.catalog-modal__nav-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 20px;
  background: transparent;
  border: none;
  color: #a0aec0;
  font-size: 14px;
  text-align: left;
  cursor: pointer;
  transition: all 0.2s ease;
  border-radius: 10px;
  margin: 2px 0;
}

.catalog-modal__nav-item:hover {
  background: #1a1a1a;
  color: #ffffff;
}

.catalog-modal__nav-item.active {
  background: #ffffff;
  color: #000000;
  font-weight: 600;
}

.catalog-modal__nav-icon {
  width: 24px;
  height: 24px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
  transition: background 0.2s;
}

.catalog-modal__nav-item.active .catalog-modal__nav-icon {
  background: #000000;
  color: #ffffff;
}

.catalog-modal__nav-text {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.catalog-modal__nav-arrow {
  opacity: 0;
  transform: translateX(-8px);
  transition: all 0.2s ease;
  font-size: 16px;
  font-weight: 600;
}

.catalog-modal__nav-item:hover .catalog-modal__nav-arrow,
.catalog-modal__nav-item.active .catalog-modal__nav-arrow {
  opacity: 1;
  transform: translateX(0);
}

.catalog-modal__sidebar-footer {
  padding: 20px 28px;
  border-top: 1px solid #1a1a1a;
}

.catalog-modal__companies {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #718096;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.2s;
}

.catalog-modal__companies:hover {
  color: #ffffff;
}

/* === Content === */
.catalog-modal__content {
  padding: 32px 40px;
  overflow-y: auto;
  position: relative;
  background: #ffffff;
}

.catalog-modal__close {
  position: absolute;
  top: 24px;
  right: 24px;
  width: 44px;
  height: 44px;
  border: none;
  background: #f7fafc;
  border-radius: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: #4a5568;
  transition: all 0.2s ease;
  z-index: 10;
}

.catalog-modal__close:hover {
  background: #000000;
  color: #ffffff;
  transform: rotate(90deg);
}

.catalog-modal__header {
  margin-bottom: 40px;
  padding-bottom: 24px;
  border-bottom: 1px solid #e2e8f0;
}

.catalog-modal__title {
  font-size: 32px;
  font-weight: 800;
  color: #000000;
  margin: 0 0 10px;
  letter-spacing: -0.5px;
}

.catalog-modal__subtitle {
  color: #718096;
  font-size: 15px;
  margin: 0;
  line-height: 1.5;
}

/* === Columns Grid === */
.catalog-modal__columns {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px 40px;
}

.catalog-modal__col {
  display: flex;
  flex-direction: column;
}

.catalog-modal__col-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 18px;
  padding-bottom: 12px;
  border-bottom: 2px solid #000000;
}

.catalog-modal__col-dot {
  width: 8px;
  height: 8px;
  background: #000000;
  border-radius: 50%;
  flex-shrink: 0;
}

.catalog-modal__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.catalog-modal__item {
  width: 100%;
  text-align: left;
  padding: 10px 14px;
  background: transparent;
  border: none;
  border-radius: 8px;
  color: #4a5568;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.15s ease;
  position: relative;
}

.catalog-modal__item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%) scaleX(0);
  width: 3px;
  height: 20px;
  background: #000000;
  border-radius: 0 4px 4px 0;
  transition: transform 0.15s ease;
  transform-origin: left;
}

.catalog-modal__item:hover {
  background: #f7fafc;
  color: #000000;
  padding-left: 18px;
}

.catalog-modal__item:hover::before {
  transform: translateY(-50%) scaleX(1);
}

.catalog-modal__item:active {
  background: #000000;
  color: #ffffff;
}

/* === Footer === */
.catalog-modal__footer {
  margin-top: 48px;
  padding-top: 24px;
  border-top: 1px solid #e2e8f0;
  text-align: center;
}

.catalog-modal__footer p {
  color: #718096;
  font-size: 14px;
  margin: 0;
}

.catalog-modal__footer a {
  color: #000000;
  text-decoration: none;
  font-weight: 600;
  transition: opacity 0.2s;
}

.catalog-modal__footer a:hover {
  opacity: 0.7;
}

/* === Transitions === */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* === Scrollbar === */
.catalog-modal__nav::-webkit-scrollbar,
.catalog-modal__content::-webkit-scrollbar {
  width: 6px;
}

.catalog-modal__nav::-webkit-scrollbar-track,
.catalog-modal__content::-webkit-scrollbar-track {
  background: transparent;
}

.catalog-modal__nav::-webkit-scrollbar-thumb {
  background: #2d2d2d;
  border-radius: 3px;
}

.catalog-modal__content::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 3px;
}

/* === Responsive === */
@media (max-width: 1024px) {
  .catalog-modal__window {
    grid-template-columns: 1fr;
    max-height: 90vh;
  }
  
  .catalog-modal__sidebar {
    border-right: none;
    border-bottom: 1px solid #1a1a1a;
    max-height: 220px;
  }
  
  .catalog-modal__nav {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 12px 16px;
  }
  
  .catalog-modal__nav-item {
    width: auto;
    padding: 10px 16px;
    font-size: 13px;
  }
  
  .catalog-modal__nav-icon,
  .catalog-modal__nav-arrow {
    display: none;
  }
  
  .catalog-modal__sidebar-footer {
    display: none;
  }
  
  .catalog-modal__content {
    padding: 24px;
  }
  
  .catalog-modal__columns {
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
  }
  
  .catalog-modal__title {
    font-size: 26px;
  }
}

@media (max-width: 640px) {
  .catalog-modal {
    padding-top: 20px;
    align-items: center;
  }
  
  .catalog-modal__window {
    width: 96vw;
    max-height: 85vh;
    border-radius: 16px;
  }
  
  .catalog-modal__sidebar {
    max-height: 180px;
  }
  
  .catalog-modal__nav-item {
    padding: 8px 12px;
    font-size: 12px;
  }
  
  .catalog-modal__content {
    padding: 20px;
  }
  
  .catalog-modal__columns {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  
  .catalog-modal__col-title {
    font-size: 14px;
  }
  
  .catalog-modal__item {
    font-size: 13px;
    padding: 8px 12px;
  }
  
  .catalog-modal__title {
    font-size: 22px;
  }
  
  .catalog-modal__subtitle {
    font-size: 14px;
  }
}
</style>