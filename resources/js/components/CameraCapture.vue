<template>
    <div>
        <input ref="fileInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onFile" />
        <button type="button" @click="fileInput.click()"
            class="flex w-full flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-teal-300 bg-white py-16 text-teal-600">
            <svg class="h-12 w-12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="text-lg font-semibold">Take a photo</span>
            <span class="text-sm text-slate-500">or tap to choose from gallery</span>
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['captured'])
const fileInput = ref(null)

function onFile(event) {
    const file = event.target.files?.[0]
    if (file) {
        emit('captured', file)
    }
    event.target.value = ''
}
</script>
