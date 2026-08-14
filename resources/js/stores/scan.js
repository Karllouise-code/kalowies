import { defineStore } from 'pinia'
import { api } from '../services/api'

const POLL_INTERVAL = 2000

export const useScanStore = defineStore('scan', {
    state: () => ({
        meal: null,
        status: 'idle', // idle | processing | ready | failed | cancelled
        error: null,
        timer: null,
    }),
    actions: {
        async start(formData) {
            this.stop()
            this.status = 'processing'
            this.error = null
            try {
                const { meal } = await api.scanMeal(formData)
                this.meal = meal
                this.startPolling()
            } catch (e) {
                this.status = 'failed'
                this.error = e.response?.data?.message ?? e.message ?? 'Could not upload the photo.'
                this.stop()
                this.meal = null
            }
        },
        startPolling() {
            this.stop()
            this.timer = setInterval(async () => {
                await this.poll()
            }, POLL_INTERVAL)
        },
        async poll() {
            if (!this.meal) return
            try {
                const { meal } = await api.meal(this.meal.id)
                this.meal = meal
                if (meal.status === 'ready') {
                    this.status = 'ready'
                    this.stop()
                } else if (meal.status === 'failed') {
                    this.status = 'failed'
                    this.error = meal.note || 'Could not analyze this image.'
                    this.stop()
                } else if (meal.status === 'cancelled') {
                    this.status = 'cancelled'
                    this.stop()
                }
            } catch (e) {
                this.status = 'failed'
                this.error = 'Could not check scan status.'
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
            try {
                const { meal } = await api.confirmMeal(this.meal.id)
                this.stop()
                this.meal = null
                this.status = 'idle'
                this.error = null
                return meal
            } catch (e) {
                this.error = e.response?.data?.message ?? 'Could not confirm this meal.'
                throw e
            }
        },
    },
})
