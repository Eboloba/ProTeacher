import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";  // ← Добавлено

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,  
    proxy: {
      '/api': { 
        target: 'http://localhost:8085', 
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/api/, '')
      }
    }
  }
})