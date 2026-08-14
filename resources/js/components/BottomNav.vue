<template>
    <nav class="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-md items-center justify-around py-2">
            <router-link
                v-for="tab in tabs"
                :key="tab.name"
                :to="{ name: tab.name }"
                class="flex flex-col items-center gap-1 px-4 py-1 text-xs font-medium text-slate-400"
                active-class="text-teal-600"
            >
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path v-for="d in tab.paths" :key="d" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                </svg>
                {{ tab.label }}
            </router-link>
        </div>
    </nav>
</template>

<script setup>
const tabs = [
    { name: 'today', label: 'Today', paths: ['M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'] },
    { name: 'scan', label: 'Scan', paths: ['M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z', 'M15 13a3 3 0 11-6 0 3 3 0 016 0z'] },
    { name: 'history', label: 'History', paths: ['M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'] },
    { name: 'profile', label: 'Profile', paths: ['M16 7a4 4 0 11-8 0 4 4 0 018 0z', 'M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'] },
]
</script>
