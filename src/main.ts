import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'

// Create Vue app with Pinia store
const app = createApp(App)
const pinia = createPinia()

// Add Nextcloud translation methods globally
app.mixin({ methods: { t, n } })

// Install Pinia for state management
app.use(pinia)

// Mount the app
app.mount('#door-estimator-app')
