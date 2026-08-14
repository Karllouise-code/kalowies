<template>
    <div>
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-slate-600">{{ label }}</span>
            <span class="text-slate-500">{{ displayValue }}</span>
        </div>
        <div class="mt-1 h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
            <div class="h-full rounded-full transition-all" :class="color" :style="{ width: `${pct}%` }"></div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: { type: String, required: true },
    value: { type: Number, required: true },
    goal: { type: Number, default: null },
    color: { type: String, default: 'bg-teal-600' },
})

const pct = computed(() => {
    if (!props.goal) return 0
    return Math.min(100, Math.round((props.value / props.goal) * 100))
})

const displayValue = computed(() =>
    props.goal ? `${Math.round(props.value)} / ${props.goal}` : `${Math.round(props.value)}`,
)
</script>
