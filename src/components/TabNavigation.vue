<template>
  <nav :class="navClasses" role="navigation" :aria-label="$t('Main navigation')">
    <div class="nav-container">
      <div class="nav-brand">
        <span class="nav-icon" aria-hidden="true">🚪</span>
        <h1 class="nav-title">
          {{ $t('Door Estimator') }}
        </h1>
      </div>
      
      <div class="nav-controls">
        <!-- Mobile menu button -->
        <button
          v-if="isMobile"
          :class="mobileMenuButtonClasses"
          type="button"
          :aria-expanded="mobileMenuOpen"
          :aria-label="$t('Toggle navigation menu')"
          @click="toggleMobileMenu"
        >
          <span class="hamburger-icon" :class="{ 'hamburger-open': mobileMenuOpen }">
            <span></span>
            <span></span>
            <span></span>
          </span>
        </button>

        <!-- Navigation tabs -->
        <div 
          :class="tabListClasses"
          role="tablist" 
          :aria-label="$t('Application sections')"
        >
          <button
            v-for="tab in tabs"
            :key="tab.key"
            role="tab"
            :class="getTabClasses(tab.key)"
            :aria-selected="activeTab === tab.key"
            :aria-controls="`${tab.key}-panel`"
            :aria-label="tab.ariaLabel || `${$t('Switch to')} ${tab.label}`"
            :tabindex="activeTab === tab.key ? 0 : -1"
            @click="handleTabClick(tab.key)"
            @keydown="handleTabKeydown($event, tab.key)"
          >
            <span class="tab-icon" aria-hidden="true">
              <component :is="tab.icon" />
            </span>
            <span class="tab-label">{{ $t(tab.label) }}</span>
          </button>
        </div>
        
        <!-- Theme toggle button -->
        <button
          :class="themeButtonClasses"
          type="button"
          :title="isDarkMode ? $t('Switch to light mode') : $t('Switch to dark mode')"
          :aria-label="isDarkMode ? $t('Switch to light mode') : $t('Switch to dark mode')"
          :aria-pressed="isDarkMode"
          @click="toggleTheme"
        >
          <span class="theme-icon" aria-hidden="true">
            {{ isDarkMode ? '🌙' : '☀️' }}
          </span>
          <span class="theme-label sr-only">
            {{ isDarkMode ? $t('Dark mode') : $t('Light mode') }}
          </span>
        </button>
      </div>
    </div>

    <!-- Mobile menu overlay -->
    <div 
      v-if="isMobile && mobileMenuOpen" 
      class="mobile-menu-overlay"
      @click="closeMobileMenu"
    ></div>
  </nav>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useThemeStore } from '../stores'

export interface Tab {
  key: string
  label: string
  icon: any
  ariaLabel?: string
}

interface Props {
  activeTab: string
  tabs: Tab[]
}

interface Emits {
  (e: 'tab-change', tab: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const themeStore = useThemeStore()
const isDarkMode = computed(() => themeStore.isDarkMode)

// Mobile menu state
const mobileMenuOpen = ref(false)
const isMobile = ref(false)

// Responsive breakpoint detection
const updateIsMobile = () => {
  if (typeof window !== 'undefined') {
    isMobile.value = window.innerWidth < 768
    if (!isMobile.value) {
      mobileMenuOpen.value = false
    }
  }
}

const toggleTheme = () => {
  themeStore.toggleTheme()
}

const toggleMobileMenu = () => {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

const closeMobileMenu = () => {
  mobileMenuOpen.value = false
}

const handleTabClick = (tabKey: string) => {
  emit('tab-change', tabKey)
  closeMobileMenu()
}

const handleTabKeydown = (event: KeyboardEvent, tabKey: string) => {
  const currentIndex = props.tabs.findIndex(tab => tab.key === props.activeTab)
  let newIndex = currentIndex

  switch (event.key) {
    case 'ArrowLeft':
    case 'ArrowUp':
      event.preventDefault()
      newIndex = currentIndex > 0 ? currentIndex - 1 : props.tabs.length - 1
      break
    case 'ArrowRight':
    case 'ArrowDown':
      event.preventDefault()
      newIndex = currentIndex < props.tabs.length - 1 ? currentIndex + 1 : 0
      break
    case 'Home':
      event.preventDefault()
      newIndex = 0
      break
    case 'End':
      event.preventDefault()
      newIndex = props.tabs.length - 1
      break
    case 'Enter':
    case ' ':
      event.preventDefault()
      handleTabClick(tabKey)
      return
  }

  if (newIndex !== currentIndex) {
    const newTab = props.tabs[newIndex]
    if (newTab) {
      emit('tab-change', newTab.key)
    }
  }
}

// Simple icon components
const EstimatorIcon = () => '📊'
const AdminIcon = () => '⚙️'

// Computed classes
const navClasses = computed(() => [
  'nav',
  isDarkMode.value ? 'nav--dark' : 'nav--light',
  {
    'nav--mobile-menu-open': mobileMenuOpen.value
  }
])

const tabListClasses = computed(() => [
  'tab-list',
  {
    'tab-list--mobile': isMobile.value,
    'tab-list--mobile-open': isMobile.value && mobileMenuOpen.value
  }
])

const themeButtonClasses = computed(() => [
  'theme-button',
  isDarkMode.value ? 'theme-button--dark' : 'theme-button--light'
])

const mobileMenuButtonClasses = computed(() => [
  'mobile-menu-button',
  isDarkMode.value ? 'mobile-menu-button--dark' : 'mobile-menu-button--light'
])

const getTabClasses = (tabKey: string) => [
  'tab',
  tabKey === 'estimator' ? 'tab--estimator' : 'tab--admin',
  props.activeTab === tabKey ? 'tab--active' : 'tab--inactive',
  isDarkMode.value ? 'tab--dark' : 'tab--light'
]

// Placeholder translation function
const $t = (text: string) => text

// Lifecycle
onMounted(() => {
  updateIsMobile()
  window.addEventListener('resize', updateIsMobile)
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateIsMobile)
  }
})
</script>

<style scoped>
.nav {
  position: sticky;
  top: 0;
  z-index: 40;
  border-bottom: 1px solid;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: background-color 0.2s, border-color 0.2s;
}

.nav--light {
  background: white;
  border-color: #e5e7eb;
}

.nav--dark {
  background: #1f2937;
  border-color: #374151;
}

.nav-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 64px;
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 16px;
  position: relative;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.nav-icon {
  font-size: 24px;
}

.nav-title {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  color: inherit;
}

.nav-controls {
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Mobile menu button */
.mobile-menu-button {
  display: none;
  padding: 8px;
  border: none;
  background: none;
  cursor: pointer;
  border-radius: 4px;
  min-height: 44px;
  min-width: 44px;
  align-items: center;
  justify-content: center;
}

.mobile-menu-button--light:hover {
  background: #f3f4f6;
}

.mobile-menu-button--dark:hover {
  background: #374151;
}

.hamburger-icon {
  display: flex;
  flex-direction: column;
  width: 20px;
  height: 16px;
  justify-content: space-between;
}

.hamburger-icon span {
  display: block;
  height: 2px;
  width: 100%;
  background: currentColor;
  border-radius: 1px;
  transition: all 0.3s ease;
}

.hamburger-open span:nth-child(1) {
  transform: rotate(45deg) translate(5px, 5px);
}

.hamburger-open span:nth-child(2) {
  opacity: 0;
}

.hamburger-open span:nth-child(3) {
  transform: rotate(-45deg) translate(7px, -6px);
}

/* Tab list */
.tab-list {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tab-list--mobile {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: inherit;
  border-bottom: 1px solid;
  border-color: inherit;
  flex-direction: column;
  gap: 0;
  padding: 8px 0;
  transform: translateY(-100%);
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.tab-list--mobile-open {
  transform: translateY(0);
  opacity: 1;
  visibility: visible;
}

/* Mobile menu overlay */
.mobile-menu-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: -1;
}

/* Tabs */
.tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  min-height: 44px;
  position: relative;
}

.tab:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

.tab-icon {
  font-size: 16px;
  flex-shrink: 0;
}

.tab-label {
  white-space: nowrap;
}

/* Tab states */
.tab--active.tab--estimator {
  background: #dbeafe;
  color: #1d4ed8;
}

.tab--active.tab--admin {
  background: #f3e8ff;
  color: #7c3aed;
}

.tab--inactive.tab--light {
  color: #6b7280;
  background: transparent;
}

.tab--inactive.tab--light:hover {
  color: #374151;
  background: #f3f4f6;
}

.tab--inactive.tab--dark {
  color: #d1d5db;
  background: transparent;
}

.tab--inactive.tab--dark:hover {
  color: #f9fafb;
  background: #374151;
}

/* Dark mode active tabs */
.tab--active.tab--estimator.tab--dark {
  background: #1e3a8a;
  color: #93c5fd;
}

.tab--active.tab--admin.tab--dark {
  background: #581c87;
  color: #c4b5fd;
}

/* Theme button */
.theme-button {
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  min-height: 44px;
  min-width: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.theme-button:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

.theme-button--light {
  background: #f3f4f6;
  color: #374151;
}

.theme-button--light:hover {
  background: #e5e7eb;
}

.theme-button--dark {
  background: #374151;
  color: #d1d5db;
}

.theme-button--dark:hover {
  background: #4b5563;
}

.theme-icon {
  font-size: 16px;
}

/* Screen reader only content */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Mobile responsive design */
@media (max-width: 767px) {
  .nav-container {
    padding: 0 12px;
  }
  
  .nav-brand {
    gap: 8px;
  }
  
  .nav-title {
    font-size: 18px;
  }
  
  .nav-controls {
    gap: 8px;
  }
  
  .mobile-menu-button {
    display: flex;
  }
  
  .tab-list:not(.tab-list--mobile) {
    display: none;
  }
  
  .tab-list--mobile .tab {
    width: 100%;
    justify-content: flex-start;
    padding: 12px 16px;
    border-radius: 0;
    margin: 0;
  }
  
  .theme-button {
    padding: 6px 10px;
    min-width: 40px;
    min-height: 40px;
  }
  
  .theme-icon {
    font-size: 14px;
  }
}

/* Tablet responsive design */
@media (min-width: 768px) and (max-width: 1023px) {
  .nav-container {
    padding: 0 16px;
  }
  
  .tab {
    padding: 8px 14px;
    font-size: 13px;
  }
  
  .tab-icon {
    font-size: 14px;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .tab {
    border: 2px solid transparent;
  }
  
  .tab--active {
    border-color: currentColor;
  }
  
  .theme-button {
    border: 2px solid currentColor;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .nav,
  .tab,
  .theme-button,
  .tab-list--mobile,
  .hamburger-icon span {
    transition: none;
  }
}

/* Print styles */
@media print {
  .nav {
    position: static;
    box-shadow: none;
    border-bottom: 1px solid #000;
  }
  
  .theme-button,
  .mobile-menu-button {
    display: none;
  }
}
</style>