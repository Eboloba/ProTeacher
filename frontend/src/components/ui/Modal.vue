<script setup>
import { onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  closeOnBackdrop: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'close'])

function handleBackdropClick(e) {
  if (e.target === e.currentTarget && props.closeOnBackdrop) {
    emit('update:modelValue', false)
    emit('close')
  }
}

function handleEscape(e) {
  if (e.key === 'Escape') {
    emit('update:modelValue', false)
    emit('close')
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal__backdrop" @click.self="handleBackdropClick">
        <div class="modal__container">
          <slot></slot>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal__backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
}

.modal__container {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal__container,
.modal-leave-to .modal__container {
  transform: scale(0.95) translateY(-20px);
}
</style>