<template>
    <div class="flex min-h-screen flex-col justify-center px-6">
        <div class="mb-8 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-teal-600 text-3xl font-bold text-white">K</div>
            <h1 class="mt-4 text-3xl font-bold text-slate-800">KaloWies</h1>
            <p class="mt-1 text-slate-500">See your calories clearly.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <input v-model="email" type="email" placeholder="Email" required
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />
            <input v-model="password" type="password" placeholder="Password" required
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <button type="submit" :disabled="loading"
                class="w-full rounded-xl bg-teal-600 py-4 font-semibold text-white disabled:opacity-50">Log in</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            No account?
            <router-link :to="{ name: 'register' }" class="font-medium text-teal-600">Sign up</router-link>
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
    loading.value = true
    error.value = ''
    try {
        await auth.login({ email: email.value, password: password.value })
        router.push({ name: 'today' })
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not log in.'
    } finally {
        loading.value = false
    }
}
</script>
