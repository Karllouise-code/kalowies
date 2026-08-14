<template>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">{{ labelFor(meal.type) }}</span>
                <span v-if="meal.source === 'scan'" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">Scan</span>
            </div>
            <button type="button" @click="onDelete" class="text-slate-400 hover:text-red-500">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>

        <p class="mt-2 font-medium text-slate-800">{{ meal.note || itemsText }}</p>
        <p class="mt-1 text-sm text-slate-500">
            <span class="font-semibold text-slate-800">{{ Math.round(meal.total_calories) }}</span> kcal ·
            P {{ Math.round(meal.total_protein) }} · C {{ Math.round(meal.total_carbs) }} · F {{ Math.round(meal.total_fat) }}
        </p>

        <ul v-if="meal.items?.length" class="mt-2 space-y-1 text-sm text-slate-500">
            <li v-for="item in meal.items" :key="item.id">{{ item.name }} — {{ Math.round(item.grams) }}g</li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    meal: { type: Object, required: true },
})
const emit = defineEmits(['deleted'])

const itemsText = computed(() => props.meal.items?.map((i) => i.name).join(', ') || 'Meal')

function labelFor(type) {
    return type.charAt(0).toUpperCase() + type.slice(1)
}

function onDelete() {
    emit('deleted', props.meal)
}
</script>
