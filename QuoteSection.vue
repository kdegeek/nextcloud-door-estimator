<template>
  <div :class="[
    darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200',
    'rounded-lg shadow-sm border p-6 mb-6 quote-section'
  ]">
    <h3 :class="['text-lg font-semibold mb-4', darkMode ? 'text-gray-100' : 'text-gray-800']">{{ title }}</h3>
    <div v-for="(item, index) in items" :key="item.id"
      class="grid grid-cols-12 gap-3 mb-4 items-center p-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-gray-600">
      <div :class="['text-sm font-bold text-center bg-gray-100 dark:bg-gray-600 rounded-md px-3 py-2', darkMode ? 'text-gray-300' : 'text-gray-600']">
        {{ item.id }}
      </div>
      <template v-if="hasFrameType">
        <select
          class="col-span-2 px-3 py-2 border rounded-md text-sm"
          :class="darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'"
          v-model="item.frameType"
          @change="onUpdateQuoteItem(index, 'frameType', item.frameType)"
        >
          <option value="HM Drywall">HM Drywall</option>
          <option value="HM EWA">HM EWA</option>
          <option value="HM USA">HM USA</option>
        </select>
      </template>
      <select
        :class="[
          hasFrameType ? 'col-span-4' : 'col-span-6',
          'px-3 py-2 border rounded-md text-sm',
          darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'
        ]"
        v-model="item.item"
        @change="onUpdateQuoteItem(index, 'item', item.item)"
      >
        <option value="">Select item...</option>
        <option v-for="(option, idx) in availableItems(item)" :key="idx" :value="option.item">
          {{ option.item }}
        </option>
      </select>
      <input
        type="number"
        min="0"
        step="1"
        class="col-span-1 px-3 py-2 border rounded-md text-sm text-center"
        :class="darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'"
        placeholder="Qty"
        :value="item.qty"
        @input="onQtyInput($event, index)"
      />
      <div :class="['col-span-2 px-3 py-2 rounded-md text-sm text-right font-mono', darkMode ? 'bg-gray-600 text-gray-100' : 'bg-gray-50 text-gray-900']">
        ${{ (item.price || 0).toFixed(2) }}
      </div>
      <div :class="['col-span-2 px-3 py-2 rounded-md text-sm font-bold text-right font-mono', darkMode ? 'bg-blue-900 text-blue-100' : 'bg-blue-50 text-blue-900']">
        ${{ (item.total || 0).toFixed(2) }}
      </div>
    </div>
    <div :class="['border-t pt-4 mt-6', darkMode ? 'border-gray-600' : 'border-gray-200']">
      <div class="flex justify-between items-center text-sm">
        <span :class="[darkMode ? 'text-gray-300' : 'text-gray-600', 'font-medium']">Subtotal:</span>
        <span :class="[darkMode ? 'text-gray-100' : 'text-gray-900', 'font-bold text-lg font-mono']">
          ${{ calculateSectionTotal(sectionKey).toFixed(2) }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  title: string,
  sectionKey: string,
  hasFrameType?: boolean,
  quoteData: Record<string, any[]>,
  pricingData: Record<string, any>,
  darkMode: boolean,
  updateQuoteItem: (section: string, index: number, field: string, value: any) => void,
  calculateSectionTotal: (section: string) => number,
  inputError: (msg: string) => void
}>();

const items = computed(() => props.quoteData[props.sectionKey] || []);

function availableItems(item: any) {
  if (props.hasFrameType) {
    return props.pricingData[props.sectionKey]?.[item.frameType] || [];
  }
  return props.pricingData[props.sectionKey] || [];
}

function onUpdateQuoteItem(index: number, field: string, value: any) {
  props.updateQuoteItem(props.sectionKey, index, field, value);
}

function onQtyInput(e: Event, index: number) {
  const val = (e.target as HTMLInputElement).value;
  if (!/^\d*$/.test(val) || parseInt(val, 10) < 0) {
    props.inputError('Quantity must be a non-negative integer');
  } else {
    props.inputError('');
    props.updateQuoteItem(props.sectionKey, index, 'qty', val);
  }
}
</script>
