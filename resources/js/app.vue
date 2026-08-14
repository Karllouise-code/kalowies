<template>
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col bg-slate-50">
        <main class="flex-1 pb-24">
            <router-view />
        </main>
        <BottomNav />
    </div>
</template>

<script setup>
import BottomNav from './components/BottomNav.vue'
</script>
