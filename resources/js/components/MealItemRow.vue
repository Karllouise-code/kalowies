<template>
    <div class="mb-3 rounded-2xl bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <input v-model="name" @change="emitUpdate"
                class="w-full font-semibold text-slate-800 focus:outline-none" />
            <button type="button" @click="emit('remove')" class="shrink-0 text-slate-400 hover:text-red-500">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>

        <div class="mt-3 flex items-center gap-2">
            <label class="text-sm text-slate-500">Portion (g)</label>
            <input v-model.number="grams" type="number" min="1" max="3000" @change="emitUpdate"
                class="w-24 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-800 focus:outline-none" />
        </div>

        <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs text-slate-500">
            <div class="rounded-lg bg-slate-50 py-2"><p class="font-semibold text-slate-800">{{ Math.round(calories) }}</p>kcal</div>
            <div class="rounded-lg bg-slate-50 py-2"><p class="font-semibold text-slate-800">{{ protein.toFixed(1) }}</p>protein</div>
            <div class="rounded-lg bg-slate-50 py-2"><p class="font-semibold text-slate-800">{{ carbs.toFixed(1) }}</p>carbs</div>
            <div class="rounded-lg bg-slate-50 py-2"><p class="font-semibold text-slate-800">{{ fat.toFixed(1) }}</p>fat</div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({ item: { type: Object, required: true } })
const emit = defineEmits(['update', 'remove'])

const name = ref(props.item.name)
const grams = ref(props.item.grams)
const calories = ref(props.item.calories)
const protein = ref(props.item.protein)
const carbs = ref(props.item.carbs)
const fat = ref(props.item.fat)

function scale(value) {
    return (value * grams.value) / props.item.grams
}

function emitUpdate() {
    calories.value = scale(props.item.calories)
    protein.value = scale(props.item.protein)
    carbs.value = scale(props.item.carbs)
    fat.value = scale(props.item.fat)
    emit('update', {
        name: name.value,
        grams: grams.value,
        calories: Math.round(calories.value),
        protein: Math.round(protein.value * 10) / 10,
        carbs: Math.round(carbs.value * 10) / 10,
        fat: Math.round(fat.value * 10) / 10,
    })
}
</script>
