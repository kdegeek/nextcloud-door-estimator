<template>
  <div>
    <div class="text-2xl font-bold mb-4">Admin Panel</div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="(items, category) in pricingData" :key="category" class="rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-semibold mb-4 capitalize">{{ category }}</h3>
        <div class="space-y-2 max-h-64 overflow-y-auto">
          <div v-if="Array.isArray(items)" v-for="(item, idx) in items" :key="idx" class="flex justify-between items-center p-3 bg-gray-50 rounded-md">
            <span class="text-sm flex-1 mr-2">{{ item.item }}</span>
            <span class="text-sm font-bold text-green-600 font-mono">${{ item.price }}</span>
          </div>
          <div v-else class="text-sm text-gray-500">Complex category structure</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  pricingData: Object,
  darkMode: Boolean
})
</script>
