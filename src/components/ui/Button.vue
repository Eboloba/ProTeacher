<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'ghost', 'success', 'danger'].includes(v)
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v)
  },
  disabled: Boolean,
  loading: Boolean
})

const classes = computed(() => ({
  'btn': true,
  [`btn--${props.variant}`]: true,
  [`btn--${props.size}`]: true,
  'btn--disabled': props.disabled || props.loading,
  'btn--loading': props.loading
}))
</script>

<template>
  <button :class="classes" :disabled="disabled || loading">
    <span v-if="loading" class="btn__loader"></span>
    <slot></slot>
  </button>
</template>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.btn--primary {
  background: #000000;
  color: white;
}

.btn--primary:hover:not(.btn--disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.btn--secondary {
  background: #ffffff;
  color: #000000;
  border: 2px solid #000000;
}

.btn--secondary:hover:not(.btn--disabled) {
  background: #000000;
  color: #ffffff;
  transform: translateY(-2px);
}

.btn--ghost {
  background: transparent;
  border: 1px solid #e2e8f0;
  color: #4a5568;
}

.btn--ghost:hover:not(.btn--disabled) {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.btn--success {
  background: #48bb78;
  color: white;
}

.btn--danger {
  background: #f56565;
  color: white;
}

.btn--sm {
  padding: 6px 12px;
  font-size: 13px;
}

.btn--md {
  padding: 10px 20px;
  font-size: 14px;
}

.btn--lg {
  padding: 14px 28px;
  font-size: 16px;
}

.btn--disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
}

.btn__loader {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>