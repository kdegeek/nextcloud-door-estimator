<template>
  <div :class="['min-h-screen', darkMode ? 'bg-gray-900' : 'bg-gray-100', 'transition-colors']">
    <!-- Navigation -->
    <nav :class="[darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200', 'shadow-sm border-b sticky top-0 z-40']">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <span class="text-xl font-bold mr-3">🗝️</span>
            <h1 :class="[darkMode ? 'text-gray-100' : 'text-gray-900']">Door Estimator</h1>
          </div>
          <div class="flex items-center space-x-4">
            <button @click="activeTab = 'estimator'" :class="[activeTab === 'estimator' ? (darkMode ? 'bg-blue-900 text-blue-200' : 'bg-blue-100 text-blue-700') : (darkMode ? 'text-gray-300 hover:text-gray-100 hover:bg-gray-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'), 'px-4 py-2 rounded-md text-sm font-medium transition-colors']">Estimator</button>
            <button @click="activeTab = 'admin'" :class="[activeTab === 'admin' ? (darkMode ? 'bg-purple-900 text-purple-200' : 'bg-purple-100 text-purple-700') : (darkMode ? 'text-gray-300 hover:text-gray-100 hover:bg-gray-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'), 'px-4 py-2 rounded-md text-sm font-medium transition-colors']">Admin</button>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <EstimatorView v-if="activeTab === 'estimator'" v-bind="estimatorProps" />
      <AdminView v-if="activeTab === 'admin'" v-bind="adminProps" />
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import QuoteSection from './QuoteSection.vue'
// Import your priceUtils and domUtils as needed
// import { lookupPrice, calculateSectionTotal, calculateGrandTotal } from './utils/priceUtils'
// import { handleImport, handleExport } from './utils/domUtils'

const activeTab = ref('estimator')
const darkMode = ref(false)
const inputError = ref('')
const userFeedback = ref('')

const quoteData = reactive({
  doors: [ { id: 'A', item: '', qty: 0, price: 0, total: 0 }, { id: 'B', item: '', qty: 0, price: 0, total: 0 } ],
  // ...other sections
})
const markups = reactive({ doors: 15, frames: 12, hardware: 18 })
const pricingData = reactive({
  doors: [ { item: '2-0 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', price: 493 } ],
  // ...other sections
})

function updateQuoteItem(section, index, field, value) {
  quoteData[section][index][field] = value
  // Add price lookup and total calculation logic here
}
function calculateSectionTotal(sectionKey) {
  // Implement your section total logic here
  return quoteData[sectionKey].reduce((sum, item) => sum + (item.total || 0), 0)
}

const estimatorProps = {
  quoteData,
  pricingData,
  darkMode,
  updateQuoteItem,
  calculateSectionTotal,
  inputError,
  markups
}
const adminProps = {
  pricingData,
  darkMode
}
</script>

<style scoped>
/* Add any global styles here */
</style>
