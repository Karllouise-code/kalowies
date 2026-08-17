import { defineStore } from 'pinia'
import { api } from '../services/api'
import { toLocalDate } from '../utils/date'

export const useMealsStore = defineStore('meals', {
    state: () => ({
        date: toLocalDate(),
        meals: [],
        summary: null,
        loading: false,
        error: null,
    }),
    getters: {
        totals: (state) => state.summary?.totals ?? { calories: 0, protein: 0, carbs: 0, fat: 0 },
        goal: (state) => state.summary?.goal ?? null,
        remainingCalories: (state) => state.summary?.remaining?.calories ?? 0,
    },
    actions: {
        async loadDay(date = this.date) {
            this.date = date
            this.loading = true
            this.error = null
            try {
                const [summary, meals] = await Promise.all([
                    api.dailySummary(date),
                    api.mealsByDate(date),
                ])
                this.summary = summary
                this.meals = meals.meals
            } catch (e) {
                this.error = e.response?.data?.message ?? 'Failed to load data.'
            } finally {
                this.loading = false
            }
        },
        async createManual(payload) {
            await api.createMeal({ ...payload, date: this.date })
            await this.loadDay(this.date)
        },
        async deleteMeal(id) {
            await api.deleteMeal(id)
            await this.loadDay(this.date)
        },
    },
})
