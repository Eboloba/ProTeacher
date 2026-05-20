<script setup>
import { useToast } from '../../composables/useToast'

const { notifications, removeStatus } = useToast()

const icons = {
  success: '✓',
  error: '✕',
  info: 'ℹ',
  warning: '⚠'
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-container">
      <TransitionGroup name="toast">
        <div 
          v-for="toast in notifications" 
          :key="toast.id" 
          class="toast" 
          :class="`toast--${toast.type}`"
        >
          <span class="toast__icon">{{ icons[toast.type] }}</span>
          <span class="toast__message">{{ toast.message }}</span>
          <button class="toast__close" @click="removeStatus(toast.id)">×</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 12px;
  pointer-events: none;
}

.toast {
  background: white;
  padding: 14px 20px;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 320px;
  max-width: 480px;
  pointer-events: auto;
  border-left: 4px solid;
  animation: slideIn 0.3s ease;
}

.toast--success { border-left-color: #48bb78; }
.toast--error { border-left-color: #f56565; }
.toast--info { border-left-color: #667eea; }
.toast--warning { border-left-color: #ed8936; }

.toast__icon {
  font-size: 20px;
  flex-shrink: 0;
}

.toast--success .toast__icon { color: #48bb78; }
.toast--error .toast__icon { color: #f56565; }
.toast--info .toast__icon { color: #667eea; }
.toast--warning .toast__icon { color: #ed8936; }

.toast__message {
  flex: 1;
  font-size: 14px;
  color: #2d3748;
  line-height: 1.4;
}

.toast__close {
  background: none;
  border: none;
  font-size: 20px;
  color: #a0aec0;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  transition: color 0.2s;
}

.toast__close:hover {
  color: #4a5568;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(100px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100px);
}

@media (max-width: 640px) {
  .toast-container {
    left: 20px;
    right: 20px;
    top: auto;
    bottom: 20px;
  }
  
  .toast {
    min-width: auto;
    max-width: 100%;
  }
}
</style>