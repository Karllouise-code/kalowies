import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useGoalsStore = defineStore('goals', {
    state: () => ({ goal: null }),
    actions: {
        async fetch() {
            const { goal } = await api.goals()
            this.goal = goal
            return goal
        },
        async update(payload) {
            const { goal } = await api.updateGoals(payload)
            this.goal = goal
            return goal
        },
    },
})
