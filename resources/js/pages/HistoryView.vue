<template>
    <div class="px-4 py-6">
        <h1 class="text-2xl font-bold text-slate-800">History</h1>

        <input type="date" v-model="date" @change="loadDay"
            class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />

        <div class="mt-6 rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="mb-3 font-semibold text-slate-800">Last 7 days</h2>
            <div class="relative h-56">
                <WeeklyChart :data="week" />
            </div>
        </div>

        <h2 class="mt-6 text-lg font-semibold text-slate-800">Meals · {{ date }}</h2>
        <div class="mt-3 space-y-3">
            <MealCard v-for="meal in meals" :key="meal.id" :meal="meal" />
            <p v-if="!meals.length" class="text-sm text-slate-400">No meals logged for this day.</p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import { toLocalDate } from '../utils/date'
import MealCard from '../components/MealCard.vue'
import WeeklyChart from '../components/WeeklyChart.vue'

const date = ref(toLocalDate())
const meals = ref([])
const week = ref([])

function last7Days() {
    const days = []
    const today = new Date()
    for (let i = 6; i >= 0; i--) {
        const d = new Date(today)
        d.setDate(today.getDate() - i)
        days.push(toLocalDate(d))
    }
    return days
}

async function loadWeek() {
    const results = await Promise.all(last7Days().map((d) => api.dailySummary(d)))
    week.value = results.map((r) => ({ date: r.date, calories: Math.round(r.totals.calories) }))
}

async function loadDay() {
    const { meals: dayMeals } = await api.mealsByDate(date.value)
    meals.value = dayMeals
}

onMounted(async () => {
    await Promise.all([loadWeek(), loadDay()])
})
</script>
