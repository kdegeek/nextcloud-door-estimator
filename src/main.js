import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { createApp } from 'vue'
import App from './App.vue'

const app = createApp(App)
app.mixin({ methods: { t, n } })
app.mount('#door-estimator-app')