import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './app.vue'
import router from './router'

createApp(App).use(createPinia()).use(router).mount('#app')
