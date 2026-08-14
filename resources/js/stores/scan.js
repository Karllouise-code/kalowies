import { defineStore } from 'pinia'
import { api } from '../services/api'

const POLL_INTERVAL = 2000

export const useScanStore = defineStore('scan', {
    state: () => ({
        meal: null,
        status: 'idle', // idle | processing | ready | failed
        error: null,
        timer: null,
    }),
    actions: {
        async start(formData) {
            this.stop()
            this.status = 'processing'
            this.error = null
            const { meal } = await api.scanMeal(formData)
            this.meal = meal
            this.startPolling()
        },
        startPolling() {
            this.stop()
            this.timer = setInterval(async () => {
                await this.poll()
            }, POLL_INTERVAL)
        },
        async poll() {
            if (!this.meal) return
            const { meal } = await api.meal(this.meal.id)
            this.meal = meal
            if (meal.status === 'ready') {
                this.status = 'ready'
                this.stop()
            } else if (meal.status === 'failed') {
                this.status = 'failed'
                this.error = meal.note || 'Could not analyze this image.'
                this.stop()
            }
        },
        stop() {
            if (this.timer) {
                clearInterval(this.timer)
                this.timer = null
            }
        },
        async updateItem(id, data) {
            await api.updateItem(id, data)
            await this.poll()
        },
        async removeItem(id) {
            await api.deleteItem(id)
            await this.poll()
        },
        async confirm() {
            const { meal } = await api.confirmMeal(this.meal.id)
            this.stop()
            this.meal = null
            this.status = 'idle'
            this.error = null
            return meal
        },
    },
})
