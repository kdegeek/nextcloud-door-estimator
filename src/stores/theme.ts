import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  const isDarkMode = ref(false)
  
  // Initialize theme from localStorage or system preference
  const initializeTheme = () => {
    const stored = localStorage.getItem('door-estimator-theme')
    if (stored) {
      isDarkMode.value = stored === 'dark'
    } else {
      // Check system preference
      isDarkMode.value = window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    
    // Apply theme to document
    applyTheme()
  }

  const applyTheme = () => {
    if (isDarkMode.value) {
      document.documentElement.classList.add('dark')
      document.documentElement.setAttribute('data-theme', 'dark')
    } else {
      document.documentElement.classList.remove('dark')
      document.documentElement.setAttribute('data-theme', 'light')
    }
  }

  const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value
  }

  const setTheme = (dark: boolean) => {
    isDarkMode.value = dark
  }

  // Watch for theme changes and persist to localStorage
  watch(isDarkMode, (newValue) => {
    localStorage.setItem('door-estimator-theme', newValue ? 'dark' : 'light')
    applyTheme()
  })

  // Listen for system theme changes
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  mediaQuery.addEventListener('change', (e) => {
    // Only update if user hasn't manually set a preference
    const stored = localStorage.getItem('door-estimator-theme')
    if (!stored) {
      isDarkMode.value = e.matches
    }
  })

  return {
    isDarkMode,
    initializeTheme,
    toggleTheme,
    setTheme,
  }
})