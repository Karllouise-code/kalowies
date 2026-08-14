import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useMealsStore = defineStore('meals', {
    state: () => ({
        date: new Date().toISOString().slice(0, 10),
        meals: [],
        summary: null,
        loading: false,
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
            try {
                const [summary, meals] = await Promise.all([
                    api.dailySummary(date),
                    api.mealsByDate(date),
                ])
                this.summary = summary
                this.meals = meals.meals
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
