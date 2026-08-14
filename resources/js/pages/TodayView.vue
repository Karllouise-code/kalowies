<template>
    <div class="px-4 py-6">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Today</h1>
                <p class="text-sm text-slate-500">{{ formattedDate }}</p>
            </div>
            <button @click="$router.push({ name: 'scan' })"
                class="flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Take photo
            </button>
        </header>

        <div v-if="goal" class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
            <div class="flex items-end justify-between">
                <div>
                    <p class="text-4xl font-bold text-slate-800">{{ Math.round(totals.calories) }}</p>
                    <p class="text-sm text-slate-500">of {{ goal.calorie_goal }} kcal</p>
                </div>
                <p class="text-sm font-semibold text-emerald-600">{{ remainingCalories }} kcal left</p>
            </div>
            <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-slate-200">
                <div class="h-full rounded-full bg-teal-600 transition-all" :style="{ width: `${caloriePct}%` }"></div>
            </div>
            <div v-if="goal.protein_grams || goal.carbs_grams || goal.fat_grams" class="mt-5 space-y-3">
                <MacroBar label="Protein" :value="totals.protein" :goal="goal.protein_grams" color="bg-emerald-500" />
                <MacroBar label="Carbs" :value="totals.carbs" :goal="goal.carbs_grams" color="bg-amber-500" />
                <MacroBar label="Fat" :value="totals.fat" :goal="goal.fat_grams" color="bg-rose-500" />
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Meals</h2>
            <button @click="showForm = !showForm" class="text-sm font-medium text-teal-600">{{ showForm ? 'Close' : '+ Add meal' }}</button>
        </div>

        <form v-if="showForm" class="mt-3 rounded-2xl bg-white p-4 shadow-sm">
            <select v-model="form.type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none">
                <option v-for="t in mealTypes" :key="t" :value="t">{{ labelFor(t) }}</option>
            </select>
            <input v-model="form.name" placeholder="What did you eat? (e.g. Oatmeal with banana)"
                class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none" />
            <div class="mt-3 grid grid-cols-2 gap-3">
                <input v-model.number="form.grams" type="number" min="0.1" max="3000" placeholder="Grams"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none" />
                <input v-model.number="form.calories" type="number" min="0" max="2000" placeholder="Calories"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none" />
                <input v-model.number="form.protein" type="number" min="0" max="500" placeholder="Protein (g)"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none" />
                <input v-model.number="form.carbs" type="number" min="0" max="500" placeholder="Carbs (g)"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none" />
                <input v-model.number="form.fat" type="number" min="0" max="500" placeholder="Fat (g)"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none" />
            </div>
            <button @click.prevent="submitManual" class="mt-4 w-full rounded-xl bg-teal-600 py-3 font-semibold text-white">Log meal</button>
        </form>

        <div class="mt-4 space-y-3">
            <p v-if="loading" class="text-sm text-slate-400">Loading…</p>
            <MealCard v-for="meal in mealsStore.meals" :key="meal.id" :meal="meal" @deleted="onDeleted" />
            <p v-if="!loading && !mealsStore.meals.length" class="text-sm text-slate-400">No meals logged yet. Take a photo to get started.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useMealsStore } from '../stores/meals'
import MacroBar from '../components/MacroBar.vue'
import MealCard from '../components/MealCard.vue'

const mealsStore = useMealsStore()

const mealTypes = ['breakfast', 'snack', 'lunch', 'dinner']
const showForm = ref(false)
const form = reactive({ type: 'breakfast', name: '', grams: null, calories: null, protein: null, carbs: null, fat: null })

const formattedDate = computed(() =>
    new Date(mealsStore.date + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }),
)

const caloriePct = computed(() => {
    if (!mealsStore.goal?.calorie_goal) return 0
    return Math.min(100, Math.round((mealsStore.totals.calories / mealsStore.goal.calorie_goal) * 100))
})

function labelFor(type) {
    return type.charAt(0).toUpperCase() + type.slice(1)
}

async function submitManual() {
    const item = {
        name: form.name,
        grams: form.grams ?? 1,
        calories: form.calories ?? 0,
        protein: form.protein ?? 0,
        carbs: form.carbs ?? 0,
        fat: form.fat ?? 0,
    }
    await mealsStore.createManual({ type: form.type, items: [item] })
    Object.assign(form, { name: '', grams: null, calories: null, protein: null, carbs: null, fat: null })
    showForm.value = false
}

async function onDeleted(meal) {
    await mealsStore.deleteMeal(meal.id)
}

onMounted(() => mealsStore.loadDay())
</script>
