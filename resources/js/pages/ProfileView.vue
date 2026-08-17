<template>
    <div class="px-4 py-6">
        <h1 class="text-2xl font-bold text-slate-800">Profile</h1>

        <div class="mt-4 rounded-2xl bg-white p-4 shadow-sm">
            <p class="font-semibold text-slate-800">{{ auth.user?.name }}</p>
            <p class="text-sm text-slate-500">{{ auth.user?.email }}</p>
        </div>

        <form class="mt-4 rounded-2xl bg-white p-4 shadow-sm" @submit.prevent="save">
            <h2 class="font-semibold text-slate-800">Daily goals</h2>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="text-sm text-slate-500">Calorie goal</label>
                    <input v-model.number="form.calorie_goal" type="number" min="500" max="10000" required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-slate-800 focus:outline-none" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div v-for="(label, key) in macroFields" :key="key">
                        <label class="text-sm text-slate-500">{{ label }}</label>
                        <input v-model.number="form[key]" type="number" min="0" max="500"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-slate-800 focus:outline-none" />
                    </div>
                </div>
            </div>
            <p v-if="saved" class="mt-3 text-sm text-emerald-600">Saved.</p>
            <button type="submit" class="mt-4 w-full rounded-xl bg-teal-600 py-3 font-semibold text-white">Save goals</button>
        </form>

        <button @click="logout" class="mt-6 w-full rounded-xl border border-red-200 bg-white py-3 font-semibold text-red-600">Log out</button>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useGoalsStore } from '../stores/goals'

const router = useRouter()
const auth = useAuthStore()
const goals = useGoalsStore()

const macroFields = { protein_grams: 'Protein (g)', carbs_grams: 'Carbs (g)', fat_grams: 'Fat (g)' }
const form = reactive({ calorie_goal: 2000, protein_grams: null, carbs_grams: null, fat_grams: null })
const saved = ref(false)

onMounted(async () => {
    const goal = await goals.fetch()
    Object.assign(form, goal)
})

async function save() {
    saved.value = false
    await goals.update({ ...form })
    saved.value = true
}

async function logout() {
    await auth.logout()
    router.push({ name: 'login' })
}
</script>
