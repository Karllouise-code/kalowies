<template>
    <div class="px-4 py-6">
        <h1 class="text-2xl font-bold text-slate-800">Scan a meal</h1>
        <p class="mt-1 text-sm text-slate-500">Snap a photo and KaloWies will estimate calories and macros.</p>

        <div v-if="stage === 'capture'" class="mt-6">
            <CameraCapture @captured="onCaptured" />
        </div>

        <template v-else-if="stage === 'preview'">
            <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow">
                <img :src="previewUrl" alt="Food preview" class="max-h-96 w-full object-cover" />
            </div>
            <div class="mt-4 space-y-3">
                <select v-model="mealType" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-800 focus:outline-none">
                    <option v-for="t in mealTypes" :key="t" :value="t">{{ labelFor(t) }}</option>
                </select>
                <button @click="analyze" class="w-full rounded-xl bg-teal-600 py-4 text-lg font-semibold text-white shadow">Analyze photo</button>
                <button @click="reset" class="w-full rounded-xl border border-slate-300 bg-white py-3 font-medium text-slate-600">Retake photo</button>
            </div>
        </template>

        <div v-else-if="stage === 'processing'" class="mt-16 flex flex-col items-center gap-4">
            <svg class="h-12 w-12 animate-spin text-teal-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <p class="font-medium text-slate-600">Analyzing your food…</p>
            <p class="text-sm text-slate-400">This can take a few seconds.</p>
        </div>

        <div v-else-if="stage === 'failed'" class="mt-16 flex flex-col items-center gap-4">
            <p class="text-center font-medium text-slate-600">{{ scan.error }}</p>
            <button @click="reset" class="rounded-xl bg-teal-600 px-6 py-3 font-semibold text-white">Try again</button>
        </div>

        <div v-else-if="stage === 'editing'" class="mt-6">
            <p class="mb-3 text-sm font-medium text-slate-500">Review and adjust before logging.</p>
            <MealItemRow
                v-for="item in scan.meal.items"
                :key="item.id"
                :item="item"
                @update="(data) => scan.updateItem(item.id, data)"
                @remove="scan.removeItem(item.id)"
            />
            <button v-if="scan.meal.items?.length" @click="confirmMeal"
                class="mt-4 w-full rounded-xl bg-emerald-600 py-4 text-lg font-semibold text-white shadow">Log meal</button>
            <button v-else @click="cancelScan"
                class="mt-4 w-full rounded-xl border border-slate-300 bg-white py-3 font-medium text-slate-600">Discard scan</button>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import CameraCapture from '../components/CameraCapture.vue'
import MealItemRow from '../components/MealItemRow.vue'
import { useScanStore } from '../stores/scan'
import { useMealsStore } from '../stores/meals'

const router = useRouter()
const scan = useScanStore()
const mealsStore = useMealsStore()

const mealTypes = ['breakfast', 'snack', 'lunch', 'dinner']
const mealType = ref('lunch')
const previewUrl = ref(null)
const file = ref(null)

const stage = computed(() => {
    if (!file.value) return 'capture'
    if (scan.status === 'processing') return 'processing'
    if (scan.status === 'failed') return 'failed'
    if (scan.status === 'ready') return 'editing'
    return 'preview'
})

function labelFor(type) {
    return type.charAt(0).toUpperCase() + type.slice(1)
}

function onCaptured(f) {
    file.value = f
    previewUrl.value = URL.createObjectURL(f)
}

function reset() {
    scan.stop()
    scan.meal = null
    scan.status = 'idle'
    scan.error = null
    file.value = null
    previewUrl.value = null
}

async function analyze() {
    const formData = new FormData()
    formData.append('image', file.value)
    formData.append('date', mealsStore.date)
    formData.append('type', mealType.value)
    await scan.start(formData)
}

async function confirmMeal() {
    await scan.confirm()
    await mealsStore.loadDay(mealsStore.date)
    router.push({ name: 'today' })
}

async function cancelScan() {
    if (scan.meal) {
        await mealsStore.deleteMeal(scan.meal.id)
    }
    reset()
}

onBeforeUnmount(() => scan.stop())
</script>
