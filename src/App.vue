<template>
	<NcAppContent>
		<div :class="['min-h-screen', darkMode ? 'bg-gray-900' : 'bg-gray-100', 'transition-colors']">
			<!-- Navigation Bar -->
			<nav :class="[darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200', 'shadow-sm border-b sticky top-0 z-40']">
				<div class="flex justify-between h-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
					<div class="flex items-center">
						<span class="text-xl font-bold mr-3">🗝️</span>
						<h1 :class="[darkMode ? 'text-gray-100' : 'text-gray-900']">
							Door Estimator
						</h1>
					</div>
					<div class="flex items-center space-x-4">
						<button
							:class="[activeTab === 'estimator'
									? (darkMode ? 'bg-blue-900 text-blue-200' : 'bg-blue-100 text-blue-700')
									: (darkMode ? 'text-gray-300 hover:text-gray-100 hover:bg-gray-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'),
								'px-4 py-2 rounded-md text-sm font-medium transition-colors']"
							@click="activeTab = 'estimator'">
							Estimator
						</button>
						<button
							:class="[activeTab === 'admin'
									? (darkMode ? 'bg-purple-900 text-purple-200' : 'bg-purple-100 text-purple-700')
									: (darkMode ? 'text-gray-300 hover:text-gray-100 hover:bg-gray-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'),
								'px-4 py-2 rounded-md text-sm font-medium transition-colors']"
							@click="activeTab = 'admin'">
							Admin
						</button>
						<button
							:class="['ml-2 px-3 py-2 rounded-md text-sm font-medium transition-colors', darkMode ? 'bg-gray-700 text-gray-200' : 'bg-gray-200 text-gray-700']"
							title="Toggle dark mode"
							@click="darkMode = !darkMode">
							{{ darkMode ? '🌙' : '☀️' }}
						</button>
					</div>
				</div>
			</nav>

			<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
				<!-- Estimator View -->
				<div v-if="activeTab === 'estimator'">
					<div v-for="section in sections" :key="section.key" class="mb-8">
						<div
							class="quote-section rounded-lg shadow-md p-6 mb-4"
							:class="darkMode ? 'bg-gray-800 border-gray-700 text-gray-100' : 'bg-white border-gray-200 text-gray-900'">
							<h2 class="text-xl font-semibold mb-4">
								{{ section.title }}
							</h2>
							<div v-if="section.hasFrameType" class="mb-4">
								<label class="block mb-1 font-medium">Frame Type:</label>
								<select
									v-model="quoteData[section.key][0].frameType"
									class="border rounded px-2 py-1"
									:class="darkMode ? 'bg-gray-700 text-gray-100 border-gray-600' : 'bg-white text-gray-900 border-gray-300'"
									@change="updateQuoteItem(section.key, 0, 'frameType', quoteData[section.key][0].frameType)">
									<option v-for="(items, type) in pricingData.frames" :key="type" :value="type">
										{{ type }}
									</option>
								</select>
							</div>
							<table class="w-full mb-4">
								<thead>
									<tr>
										<th class="text-left py-1 px-2">
											Item
										</th>
										<th class="text-left py-1 px-2">
											Qty
										</th>
										<th class="text-left py-1 px-2">
											Price
										</th>
										<th class="text-left py-1 px-2">
											Total
										</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(item, idx) in quoteData[section.key]" :key="item.id">
										<td class="py-1 px-2">
											<input
												v-model="item.item"
												class="border rounded px-2 py-1 w-full"
												:class="darkMode ? 'bg-gray-700 text-gray-100 border-gray-600' : 'bg-white text-gray-900 border-gray-300'"
												:placeholder="section.hasFrameType && idx === 0 ? 'Frame Item' : 'Item'"
												@change="updateQuoteItem(section.key, idx, 'item', item.item)">
										</td>
										<td class="py-1 px-2">
											<input
												v-model.number="item.qty"
												type="number"
												min="0"
												class="border rounded px-2 py-1 w-20"
												:class="darkMode ? 'bg-gray-700 text-gray-100 border-gray-600' : 'bg-white text-gray-900 border-gray-300'"
												@change="updateQuoteItem(section.key, idx, 'qty', item.qty)">
										</td>
										<td class="py-1 px-2">
											<input
												v-model.number="item.price"
												type="number"
												min="0"
												step="0.01"
												class="border rounded px-2 py-1 w-24"
												:class="darkMode ? 'bg-gray-700 text-gray-100 border-gray-600' : 'bg-white text-gray-900 border-gray-300'"
												@change="updateQuoteItem(section.key, idx, 'price', item.price)">
										</td>
										<td class="py-1 px-2 font-mono">
											${{ item.total ? item.total.toFixed(2) : '0.00' }}
										</td>
									</tr>
								</tbody>
							</table>
							<div class="flex justify-between items-center">
								<span :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Section Total:</span>
								<span :class="darkMode ? 'text-green-300' : 'text-green-700'">${{ sectionTotal(section.key).toFixed(2) }}</span>
							</div>
							<div v-if="inputError" class="mt-2 text-red-400 text-sm">
								{{ inputError }}
							</div>
						</div>
					</div>
					<div class="mt-8 text-right text-2xl font-bold">
						Grand Total: <span :class="darkMode ? 'text-green-300' : 'text-green-700'">${{ grandTotal.toFixed(2) }}</span>
					</div>
					<div v-if="inputError" class="mt-4 text-red-500 text-sm">
						{{ inputError }}
					</div>
				</div>

				<!-- Admin View -->
				<div v-if="activeTab === 'admin'">
					<div class="text-2xl font-bold mb-4">
						Admin Panel
					</div>
					<div class="flex flex-wrap gap-4 mb-6">
						<button
							class="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700 transition"
							@click="showImportDialog = true">
							Import Data
						</button>
						<button
							class="bg-green-600 text-white px-4 py-2 rounded-md shadow hover:bg-green-700 transition"
							@click="exportData">
							Export Data
						</button>
					</div>
					<div v-if="showImportDialog" class="mb-6">
						<textarea v-model="importText"
							rows="6"
							class="w-full p-2 border rounded mb-2"
							placeholder="Paste JSON data here" />
						<div class="flex gap-2">
							<button class="bg-blue-600 text-white px-3 py-1 rounded" @click="importData">
								Import
							</button>
							<button class="bg-gray-400 text-white px-3 py-1 rounded" @click="showImportDialog = false">
								Cancel
							</button>
						</div>
					</div>
					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
						<div v-for="(items, category) in pricingData" :key="category" class="rounded-lg shadow-sm border p-6">
							<h3 class="text-lg font-semibold mb-4 capitalize">
								{{ category }}
							</h3>
							<div class="space-y-2 max-h-64 overflow-y-auto">
								<template v-if="Array.isArray(items)">
									<div v-for="(item, idx) in items" :key="idx" class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
										<span class="text-sm flex-1 mr-2">{{ item.item }}</span>
										<span class="text-sm font-bold text-green-600 font-mono">${{ item.price }}</span>
									</div>
								</template>
								<template v-else>
									<div class="text-sm text-gray-500">
										Complex category structure
									</div>
								</template>
							</div>
						</div>
					</div>
				</div>
			</main>
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import { lookupPrice, calculateSectionTotal, calculateGrandTotal } from '../utils/priceUtils.ts'
import { handleImport, handleExport } from '../utils/domUtils.ts'

// Section definitions
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
	{ key: 'hardware', title: 'Hardware' },
]

// State
const activeTab = ref<'estimator' | 'admin'>('estimator')
const darkMode = ref(false)
const inputError = ref('')
const showImportDialog = ref(false)
const importText = ref('')

// Example initial data (should be loaded or replaced in real app)
const quoteData = reactive<Record<string, any[]>>({
	doors: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }, { id: 'B', item: '', qty: 0, price: 0, total: 0 }],
	doorOptions: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	inserts: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	frames: [{ id: 'A', item: '', frameType: 'HM Drywall', qty: 0, price: 0, total: 0 }],
	frameOptions: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	hinges: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	weatherstrip: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	closers: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	locksets: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	exitDevices: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
	hardware: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
})
const markups = reactive({ doors: 15, frames: 12, hardware: 18 })
const pricingData = reactive<Record<string, any>>({
	doors: [{ item: '2-0 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', price: 493 }],
	doorOptions: [],
	inserts: [],
	frames: { 'HM Drywall': [], 'HM EWA': [], 'HM USA': [] },
	frameOptions: [],
	hinges: [],
	weatherstrip: [],
	closers: [],
	locksets: [],
	exitDevices: [],
	hardware: [],
})

// Methods
function updateQuoteItem(section: string, index: number, field: string, value: any) {
	const item = quoteData[section][index]
	item[field] = value

	// Price lookup
	if (field === 'item' || field === 'frameType') {
		if (section === 'frames' && item.frameType) {
			item.price = lookupPrice(pricingData, section, item.item, item.frameType)
		} else {
			item.price = lookupPrice(pricingData, section, item.item)
		}
	}
	// Total calculation
	if (field === 'qty' || field === 'item' || field === 'price' || field === 'frameType') {
		item.total = (item.price || 0) * (parseInt(item.qty, 10) || 0)
	}
}

function sectionTotal(sectionKey: string) {
	return calculateSectionTotal(quoteData, markups, sectionKey)
}

const grandTotal = computed(() => calculateGrandTotal(quoteData, markups))

function importData() {
	handleImport(importText.value, (data: any) => {
		Object.assign(pricingData, data)
	}, (data: any) => {
		Object.assign(markups, data)
	}, (val: boolean) => {
		showImportDialog.value = val
	})
}

function exportData() {
	handleExport(pricingData, markups, (val: boolean) => {
		// Optionally show a dialog or feedback
	})
}
</script>

<style scoped>
/* Responsive tweaks and dark mode overrides */
.quote-section {
  transition: background 0.2s, border-color 0.2s;
}
@media (max-width: 640px) {
  .quote-section {
    padding: 1rem !important;
  }
}
</style>
