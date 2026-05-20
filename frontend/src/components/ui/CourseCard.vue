<script setup>
import { computed } from 'vue'

const props = defineProps({
  course: Object,
  compact: Boolean
})

// 🔹 Добавляем два отдельных события вместо одного общего
const emit = defineEmits(['click', 'start', 'buy'])


const priceNum = computed(() => parseFloat(props.course?.price ?? 0))
const isFree = computed(() => priceNum.value === 0 || props.course?.is_free === true)

const displayPrice = computed(() => isFree.value ? 'Бесплатно' : `${props.course.price} ₽`)
const buttonText = computed(() => isFree.value ? 'Пройти' : 'Купить')

function handleClick() {
  emit('click', props.course)
}

function handleAction(e) {
  e.stopPropagation()
  if (isFree.value) {
    emit('start', props.course)  // Для бесплатных
  } else {
    emit('buy', props.course)    // Для платных
  }
}
</script>

<template>
  <article class="course-card" :class="{ 'course-card--compact': compact }" @click="handleClick">
    <div class="course-card__thumbnail"></div>
    
    <div class="course-card__content">
      <h3 class="course-card__title">{{ course.title }}</h3>
      <p class="course-card__author">{{ course.author }}</p>
      
      <div class="course-card__stats">
        <span>★ {{ course.rating || '5.0' }}</span>
        <span>👤 {{ course.students || 100 }}</span>
        <span>⏱ {{ course.duration || '4ч' }}</span>
      </div>

      <div class="course-card__footer">
        <div class="course-card__price">
          <span v-if="!isFree" class="course-card__old-price">{{ displayPrice }}</span>
          <strong :class="{ 'course-card__free': isFree }">{{ displayPrice }}</strong>
        </div>
        
        <!-- 🔹 Кнопка теперь триггерит handleAction, который решит, что эмитить -->
        <button 
          class="course-card__action" 
          :class="isFree ? 'course-card__action--free' : 'course-card__action--paid'"
          @click="handleAction"
        >
          {{ buttonText }}
        </button>
      </div>
    </div>
  </article>
</template>

<style scoped>
/* Ваши стили остаются без изменений */
.course-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  transition: all 0.3s ease;
  cursor: pointer;
  border: 1px solid #e2e8f0;
}

.course-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
  border-color: #000000;
}

.course-card__thumbnail {
  height: 140px;
  background: linear-gradient(135deg, #1a1a1a 0%, #313131 100%);
  position: relative;
}

.course-card__thumbnail::after {
  content: '🕮';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 48px;
}

.course-card__content {
  padding: 16px;
}

.course-card__title {
  font-size: 16px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 6px;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.course-card__author {
  font-size: 13px;
  color: #718096;
  margin: 0 0 10px;
}

.course-card__stats {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: #a0aec0;
  margin-bottom: 12px;
}

.course-card__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 12px;
  border-top: 1px solid #e2e8f0;
}

.course-card__price {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.course-card__old-price {
  font-size: 12px;
  color: #a0aec0;
  text-decoration: line-through;
}

.course-card__price strong {
  font-size: 20px;
  color: #000000;
}

.course-card__free {
  color: #48bb78 !important;
}

.course-card__action {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}

.course-card__action--free {
  background: #48bb78;
  color: white;
}

.course-card__action--free:hover {
  background: #38a169;
}

.course-card__action--paid {
  background: #000000;
  color: white;
}

.course-card__action--paid:hover {
  background: #464646;
}

.course-card--compact .course-card__thumbnail {
  height: 100px;
}

.course-card--compact .course-card__title {
  font-size: 14px;
}
</style>