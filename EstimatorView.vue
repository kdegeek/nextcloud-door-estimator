<template>
  <div>
    <QuoteSection
      v-for="section in sections"
      :key="section.key"
      :title="section.title"
      :sectionKey="section.key"
      :quoteData="quoteData"
      :pricingData="pricingData"
      :darkMode="darkMode"
      :updateQuoteItem="updateQuoteItem"
      :calculateSectionTotal="calculateSectionTotal"
      :inputError="inputError"
      :hasFrameType="section.hasFrameType || false"
    />
    <div class="mt-4 text-right text-xl font-bold">
      Grand Total: ${{ calculateGrandTotal().toFixed(2) }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import QuoteSection from './QuoteSection.vue'

const props = defineProps({
  quoteData: Object,
  pricingData: Object,
  darkMode: Boolean,
  updateQuoteItem: Function,
  calculateSectionTotal: Function,
  inputError: Function,
  markups: Object
})

const sections = [
  { key: 'doors', title: 'Doors' },
  { key: 'doorOptions', title: 'Door Options' },
  { key: 'inserts', title: 'Inserts' },
  { key: 'frames', title: 'Frames', hasFrameType: true },
  { key: 'frameOptions', title: 'Frame Options' },
  { key: 'hinges', title: 'Hinges' },
  { key: 'weatherstrip', title: 'Weatherstrip' },
  { key: 'closers', title: 'Closers' },
  { key: 'locksets', title: 'Locksets' },
  { key: 'exitDevices', title: 'Exit Devices' },
  { key: 'hardware', title: 'Hardware' }
]

function calculateGrandTotal() {
  // Example: sum all section totals
  return sections.reduce((sum, section) => sum + props.calculateSectionTotal(section.key), 0)
}
</script>
