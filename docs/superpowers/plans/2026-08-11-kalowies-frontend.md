# KaloWies Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Vue 3 + Vite PWA client for KaloWies — Sanctum cookie auth, mobile-first shell, today's summary, photo-scan flow with editable results, history with a 7-day chart, goals, and PWA installability.

**Architecture:** Vue 3 SPA lives in `resources/js`, built by Vite into `public/build`, served by Laravel at the SPA catch-all route. Axios talks to the same-origin `/api` with cookies (`withCredentials` + XSRF header). Pinia stores: `auth`, `meals`, `scan` (owns the poll loop), `goals`. Bottom nav shell. `vite-plugin-pwa` generates the service worker + manifest for the offline app shell.

**Tech Stack:** Vue 3, Vite, `@vitejs/plugin-vue`, Tailwind CSS v4 (`@tailwindcss/vite`), Pinia, Vue Router, Axios, Chart.js, `vite-plugin-pwa`, Vitest + `@vue/test-utils` + jsdom.

## Global Constraints

- Requires the backend plan to be complete first: the API contract below is fixed.
- Single origin: SPA served by Laravel; no CORS config on the client.
- API contract (from backend plan):
  - `GET /sanctum/csrf-cookie` (issue XSRF cookie before login)
  - `POST /api/register`, `POST /api/login`, `POST /api/logout`, `GET /api/me` → `{user}`
  - `GET /api/meals?date=YYYY-MM-DD` → `{meals:[{id,date,type,status,source,image_url,note,total_calories,total_protein,total_carbs,total_fat,items:[{id,name,grams,calories,protein,carbs,fat}]}]}`
  - `POST /api/meals` (manual, items array) → 201 `{meal}`
  - `POST /api/meals/scan` (multipart image,date,type) → 201 `{meal}` draft
  - `GET /api/meals/{id}` → `{meal}` (poll target)
  - `DELETE /api/meals/{id}` → 204
  - `PUT /api/meals/{id}`, `POST /api/meals/{id}/confirm` → `{meal}`
  - `PUT /api/meal-items/{id}`, `DELETE /api/meal-items/{id}`
  - `GET /api/daily-summary?date=` → `{date,meals,totals:{calories,protein,carbs,fat},goal,remaining:{calories},per_meal_type}`
  - `GET /api/goals`, `PUT /api/goals` → `{goal}`
- Meal types: `breakfast|snack|lunch|dinner`. Scan statuses: `draft|processing|ready|confirmed|cancelled|failed`.
- Branding: primary teal-600 `#0d9488`, accent emerald-600 `#059669`, background slate-50 `#f8fafc`, text slate-800. White `rounded-2xl` cards, soft shadows. Bottom nav. Tagline "See your calories clearly."
- TDD: every task starts with a failing test where a test is warranted; components/pages verified via `npm run build` or Vitest.
- Commits after every task.

---

### Task 16: Frontend toolchain scaffold

**Files:**
- Modify: `package.json`
- Create: `vite.config.js`, `vitest.config.js`
- Create: `resources/css/app.css`, `resources/js/main.js`, `resources/js/app.vue`, `resources/js/router/index.js`
- Create: placeholder pages `LoginView.vue`, `RegisterView.vue`, `TodayView.vue`, `ScanView.vue`, `HistoryView.vue`, `ProfileView.vue`
- Delete: `resources/js/app.js` (default), `resources/views/welcome.blade.php`

**Interfaces:**
- Produces: a working Vite build pipeline: `npm run build` emits `public/build/` with `manifest.json`. Dev via `npm run dev` (Vite server consumed by the blade `@vite` directive). Vitest runs `resources/js/__tests__/**/*.spec.js` in jsdom.

- [ ] **Step 1: Install dependencies**

```bash
npm install
npm install vue pinia vue-router axios chart.js
npm install -D @vitejs/plugin-vue @tailwindcss/vite tailwindcss vitest @vue/test-utils jsdom
```

- [ ] **Step 2: Configure Vite**

Create `vite.config.js`:

```js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
})
```

- [ ] **Step 3: Configure Vitest**

Create `vitest.config.js`:

```js
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/__tests__/**/*.spec.js'],
    },
})
```

Update `package.json` scripts:

```json
"scripts": {
    "dev": "vite",
    "build": "vite build",
    "test": "vitest run"
}
```

- [ ] **Step 4: Write app CSS**

Replace `resources/css/app.css` with:

```css
@import "tailwindcss";
```

- [ ] **Step 5: Write the app entry, shell, and router**

Create `resources/js/main.js`:

```js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './app.vue'
import router from './router'

createApp(App).use(createPinia()).use(router).mount('#app')
```

Create `resources/js/app.vue`:

```vue
<template>
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col bg-slate-50">
        <main class="flex-1">
            <router-view />
        </main>
    </div>
</template>

<script setup>
</script>
```

Create `resources/js/router/index.js`:

```js
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    { path: '/', name: 'today', component: () => import('../pages/TodayView.vue'), meta: { requiresAuth: true } },
    { path: '/scan', name: 'scan', component: () => import('../pages/ScanView.vue'), meta: { requiresAuth: true } },
    { path: '/history', name: 'history', component: () => import('../pages/HistoryView.vue'), meta: { requiresAuth: true } },
    { path: '/profile', name: 'profile', component: () => import('../pages/ProfileView.vue'), meta: { requiresAuth: true } },
    { path: '/login', name: 'login', component: () => import('../pages/LoginView.vue'), meta: { guestOnly: true } },
    { path: '/register', name: 'register', component: () => import('../pages/RegisterView.vue'), meta: { guestOnly: true } },
]

export default createRouter({ history: createWebHistory(), routes })
```

Create six placeholder pages — each is a minimal component, e.g. `resources/js/pages/TodayView.vue`:

```vue
<template>
    <div class="p-4"><h1 class="text-2xl font-bold text-slate-800">Today</h1></div>
</template>
```

Repeat the same skeleton for `RegisterView.vue`, `ScanView.vue`, `HistoryView.vue`, `ProfileView.vue` (different heading text), and for `LoginView.vue` use the heading "Log in". These are replaced by real implementations in later tasks.

- [ ] **Step 6: Remove default Laravel frontend files**

```bash
Remove-Item resources\js\app.js, resources\views\welcome.blade.php
```

- [ ] **Step 7: Verify the build**

Run: `npm run build`
Expected: success; `public/build/manifest.json` exists listing `resources/js/main.js` and `resources/css/app.css`.

- [ ] **Step 8: Commit**

```bash
git add package.json package-lock.json vite.config.js vitest.config.js resources/css resources/js public
git commit -m "chore: scaffold Vue 3 + Vite + Tailwind frontend"
```

---

### Task 17: API client, auth store, and auth pages

**Files:**
- Create: `resources/js/services/api.js`
- Create: `resources/js/stores/auth.js`
- Replace: `resources/js/pages/LoginView.vue`, `resources/js/pages/RegisterView.vue`
- Modify: `resources/js/router/index.js`
- Test: `resources/js/__tests__/auth.store.spec.js`

**Interfaces:**
- Produces:
  - `api` object (default export from `services/api.js`) with methods: `register(data)`, `login(data)`, `logout()`, `me()`, `mealsByDate(date)`, `createMeal(data)`, `meal(id)`, `scanMeal(formData)`, `updateMeal(id,data)`, `deleteMeal(id)`, `confirmMeal(id)`, `updateItem(id,data)`, `deleteItem(id)`, `dailySummary(date)`, `goals()`, `updateGoals(data)`.
  - `useAuthStore` with state `user`, `initialized`; getter `isAuthenticated`; actions `initialize()`, `register(payload)`, `login(payload)`, `logout()`. Dispatches a global `auth:unauthorized` event on 401.
  - Router guard: `requiresAuth` → redirect to `login` if not authenticated; `guestOnly` → redirect to `today` if authenticated.
  - `LoginView` / `RegisterView` forms that call the store and navigate to `today`.

- [ ] **Step 1: Write the failing auth store test**

Create `resources/js/__tests__/auth.store.spec.js`:

```js
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const { apiMock } = vi.hoisted(() => ({
    apiMock: { register: vi.fn(), login: vi.fn(), logout: vi.fn(), me: vi.fn() },
}))

vi.mock('../services/api', () => ({ api: apiMock }))

import { useAuthStore } from '../stores/auth'

describe('auth store', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.clearAllMocks()
    })

    it('sets the user on register', async () => {
        apiMock.register.mockResolvedValue({ user: { id: 1, email: 'a@b.c' } })
        const store = useAuthStore()

        await store.register({ name: 'A', email: 'a@b.c', password: 'password' })

        expect(store.user.email).toBe('a@b.c')
        expect(store.isAuthenticated).toBe(true)
    })

    it('sets the user on login', async () => {
        apiMock.login.mockResolvedValue({ user: { id: 2, email: 'b@c.d' } })
        const store = useAuthStore()

        await store.login({ email: 'b@c.d', password: 'password' })

        expect(store.user.email).toBe('b@c.d')
    })

    it('clears the user on logout', async () => {
        apiMock.logout.mockResolvedValue({})
        const store = useAuthStore()
        store.user = { id: 1 }

        await store.logout()

        expect(store.user).toBeNull()
        expect(store.isAuthenticated).toBe(false)
    })

    it('initialize fetches me and marks initialized even on failure', async () => {
        apiMock.me.mockRejectedValue(new Error('401'))
        const store = useAuthStore()

        await store.initialize()

        expect(store.initialized).toBe(true)
        expect(store.isAuthenticated).toBe(false)
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test`
Expected: FAIL — `stores/auth` module not found (test imports `../services/api` too, which is also missing).

- [ ] **Step 3: Write the API client**

Create `resources/js/services/api.js`:

```js
import axios from 'axios'

const http = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
})

http.interceptors.response.use(
    (res) => res,
    (error) => {
        if (error.response?.status === 401) {
            window.dispatchEvent(new Event('auth:unauthorized'))
        }
        return Promise.reject(error)
    },
)

async function csrf() {
    await http.get('/sanctum/csrf-cookie')
}

export const api = {
    register: (data) => http.post('/api/register', data).then((r) => r.data),
    login: async (data) => {
        await csrf()
        const { data: body } = await http.post('/api/login', data)
        return body
    },
    logout: () => http.post('/api/logout').then((r) => r.data),
    me: () => http.get('/api/me').then((r) => r.data),

    mealsByDate: (date) => http.get('/api/meals', { params: { date } }).then((r) => r.data),
    createMeal: (data) => http.post('/api/meals', data).then((r) => r.data),
    meal: (id) => http.get(`/api/meals/${id}`).then((r) => r.data),
    scanMeal: (formData) => http.post('/api/meals/scan', formData).then((r) => r.data),
    updateMeal: (id, data) => http.put(`/api/meals/${id}`, data).then((r) => r.data),
    deleteMeal: (id) => http.delete(`/api/meals/${id}`),
    confirmMeal: (id) => http.post(`/api/meals/${id}/confirm`).then((r) => r.data),

    updateItem: (id, data) => http.put(`/api/meal-items/${id}`, data).then((r) => r.data),
    deleteItem: (id) => http.delete(`/api/meal-items/${id}`),

    dailySummary: (date) => http.get('/api/daily-summary', { params: { date } }).then((r) => r.data),

    goals: () => http.get('/api/goals').then((r) => r.data),
    updateGoals: (data) => http.put('/api/goals', data).then((r) => r.data),
}
```

- [ ] **Step 4: Write the auth store**

Create `resources/js/stores/auth.js`:

```js
import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        initialized: false,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.user),
    },
    actions: {
        async initialize() {
            try {
                const { user } = await api.me()
                this.user = user
            } catch {
                this.user = null
            } finally {
                this.initialized = true
            }
        },
        async register(payload) {
            const { user } = await api.register(payload)
            this.user = user
        },
        async login(payload) {
            const { user } = await api.login(payload)
            this.user = user
        },
        async logout() {
            try {
                await api.logout()
            } catch {
                // session may already be gone
            }
            this.user = null
        },
    },
})

window.addEventListener('auth:unauthorized', () => {
    const auth = useAuthStore()
    auth.user = null
    if (window.location.pathname !== '/login') {
        window.location.href = '/login'
    }
})
```

- [ ] **Step 5: Wire the router guard**

Replace `resources/js/router/index.js` with:

```js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
    { path: '/', name: 'today', component: () => import('../pages/TodayView.vue'), meta: { requiresAuth: true } },
    { path: '/scan', name: 'scan', component: () => import('../pages/ScanView.vue'), meta: { requiresAuth: true } },
    { path: '/history', name: 'history', component: () => import('../pages/HistoryView.vue'), meta: { requiresAuth: true } },
    { path: '/profile', name: 'profile', component: () => import('../pages/ProfileView.vue'), meta: { requiresAuth: true } },
    { path: '/login', name: 'login', component: () => import('../pages/LoginView.vue'), meta: { guestOnly: true } },
    { path: '/register', name: 'register', component: () => import('../pages/RegisterView.vue'), meta: { guestOnly: true } },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach(async (to) => {
    const auth = useAuthStore()
    if (!auth.initialized) {
        await auth.initialize()
    }
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' }
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'today' }
    }
})

export default router
```

- [ ] **Step 6: Write the auth pages**

Replace `resources/js/pages/LoginView.vue`:

```vue
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
```

Replace `resources/js/pages/RegisterView.vue`:

```vue
<template>
    <div class="flex min-h-screen flex-col justify-center px-6">
        <div class="mb-8 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-teal-600 text-3xl font-bold text-white">K</div>
            <h1 class="mt-4 text-3xl font-bold text-slate-800">Create account</h1>
            <p class="mt-1 text-slate-500">Start tracking in seconds.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <input v-model="name" type="text" placeholder="Name" required
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />
            <input v-model="email" type="email" placeholder="Email" required
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />
            <input v-model="password" type="password" placeholder="Password (min 8 chars)" required minlength="8"
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />
            <input v-model="passwordConfirmation" type="password" placeholder="Confirm password" required
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <button type="submit" :disabled="loading"
                class="w-full rounded-xl bg-teal-600 py-4 font-semibold text-white disabled:opacity-50">Sign up</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Already have an account?
            <router-link :to="{ name: 'login' }" class="font-medium text-teal-600">Log in</router-link>
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
    loading.value = true
    error.value = ''
    try {
        await auth.register({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        })
        router.push({ name: 'today' })
    } catch (e) {
        error.value = e.response?.data?.errors?.email?.[0] || 'Could not create your account.'
    } finally {
        loading.value = false
    }
}
</script>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `npm test`
Expected: 4 auth store tests PASS.

- [ ] **Step 8: Verify the build still works**

Run: `npm run build`
Expected: success.

- [ ] **Step 9: Commit**

```bash
git add resources/js/services resources/js/stores resources/js/pages resources/js/router resources/js/__tests__
git commit -m "feat: add API client, auth store, and auth pages"
```

---

### Task 18: Today view, meals store, and summary components

**Files:**
- Create: `resources/js/stores/meals.js`
- Create: `resources/js/components/BottomNav.vue`, `resources/js/components/MacroBar.vue`, `resources/js/components/MealCard.vue`
- Replace: `resources/js/pages/TodayView.vue`
- Modify: `resources/js/app.vue`
- Test: `resources/js/__tests__/MacroBar.spec.js`

**Interfaces:**
- Produces:
  - `useMealsStore` state `date`, `meals[]`, `summary`, `loading`; getters `totals`, `goal`, `remainingCalories`; actions `loadDay(date)`, `createManual(payload)`, `deleteMeal(id)`.
  - `BottomNav` (props: none) — fixed bottom bar with routes `today`, `scan`, `history`, `profile`, teal active state.
  - `MacroBar` props `{label:String, value:Number, goal:Number|null, color:String}` — width = min(100, value/goal*100)%.
  - `MealCard` props `{meal}` — type badge, name/note, totals, item list, delete button emitting `deleted`.
  - `TodayView` — header + "Take photo" button (→ `scan`), calorie progress vs goal, macro bars (only when macro goals set), remaining kcal, manual meal form (toggle), meal list.
- Consumes: `api`, `useAuthStore` (implicitly via router), `useMealsStore`.

- [ ] **Step 1: Write the failing MacroBar test**

Create `resources/js/__tests__/MacroBar.spec.js`:

```js
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MacroBar from '../components/MacroBar.vue'

describe('MacroBar', () => {
    it('renders percentage of goal as width', () => {
        const wrapper = mount(MacroBar, { props: { label: 'Protein', value: 60, goal: 120, color: 'bg-emerald-500' } })

        const bar = wrapper.get('.bg-emerald-500')
        expect(bar.attributes('style')).toContain('width: 50%')
        expect(wrapper.text()).toContain('60 / 120')
    })

    it('clamps percentage at 100', () => {
        const wrapper = mount(MacroBar, { props: { label: 'Fat', value: 500, goal: 100, color: 'bg-rose-500' } })

        const bar = wrapper.get('.bg-rose-500')
        expect(bar.attributes('style')).toContain('width: 100%')
    })

    it('shows only the value when goal is null', () => {
        const wrapper = mount(MacroBar, { props: { label: 'Protein', value: 30, goal: null } })

        expect(wrapper.text()).toContain('30')
        expect(wrapper.text()).not.toContain('null')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test`
Expected: FAIL — `components/MacroBar.vue` not found.

- [ ] **Step 3: Write the meals store**

Create `resources/js/stores/meals.js`:

```js
import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useMealsStore = defineStore('meals', {
    state: () => ({
        date: new Date().toISOString().slice(0, 10),
        meals: [],
        summary: null,
        loading: false,
    }),
    getters: {
        totals: (state) => state.summary?.totals ?? { calories: 0, protein: 0, carbs: 0, fat: 0 },
        goal: (state) => state.summary?.goal ?? null,
        remainingCalories: (state) => state.summary?.remaining?.calories ?? 0,
    },
    actions: {
        async loadDay(date = this.date) {
            this.date = date
            this.loading = true
            try {
                const [summary, meals] = await Promise.all([
                    api.dailySummary(date),
                    api.mealsByDate(date),
                ])
                this.summary = summary
                this.meals = meals.meals
            } finally {
                this.loading = false
            }
        },
        async createManual(payload) {
            await api.createMeal({ ...payload, date: this.date })
            await this.loadDay(this.date)
        },
        async deleteMeal(id) {
            await api.deleteMeal(id)
            await this.loadDay(this.date)
        },
    },
})
```

- [ ] **Step 4: Write the MacroBar component**

Create `resources/js/components/MacroBar.vue`:

```vue
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
```

- [ ] **Step 5: Write the BottomNav and MealCard**

Create `resources/js/components/BottomNav.vue`:

```vue
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
```

Create `resources/js/components/MealCard.vue`:

```vue
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
```

- [ ] **Step 6: Write the Today view**

Replace `resources/js/pages/TodayView.vue`:

```vue
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
```

- [ ] **Step 7: Mount the BottomNav in the shell**

Replace `resources/js/app.vue` with:

```vue
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
```

- [ ] **Step 8: Run tests and build**

Run: `npm test && npm run build`
Expected: MacroBar 3 tests PASS; build succeeds.

- [ ] **Step 9: Commit**

```bash
git add resources/js/stores/meals.js resources/js/components resources/js/pages/TodayView.vue resources/js/app.vue resources/js/__tests__
git commit -m "feat: add today dashboard, meals store, and summary components"
```

---

### Task 19: Photo scan flow (CameraCapture, ScanView, scan store)

**Files:**
- Create: `resources/js/stores/scan.js`
- Create: `resources/js/components/CameraCapture.vue`, `resources/js/components/MealItemRow.vue`
- Replace: `resources/js/pages/ScanView.vue`
- Test: `resources/js/__tests__/scan.store.spec.js`

**Interfaces:**
- Produces:
  - `useScanStore` state `meal`, `status` (`idle|processing|ready|failed`), `error`, `timer`; actions `start(formData)`, `poll()`, `startPolling()`, `stop()`, `updateItem(id,data)`, `removeItem(id)`, `confirm()`.
    - `start` POSTs the scan (sets status `processing`), stores the draft meal, begins a 2s `setInterval` poll.
    - `poll` GETs `/api/meals/{id}`; on `ready` → status `ready`, stop; on `failed` → status `failed`, `error` from `meal.note`, stop.
    - `confirm` POSTs `/api/meals/{id}/confirm`, resets state, returns `{meal}`.
  - `CameraCapture` emits `captured(file)` via `<input type="file" accept="image/*" capture="environment">`.
  - `MealItemRow` props `{item}`; emits `update({name,grams,calories,protein,carbs,fat})` (linear macro rescale on grams change) and `remove`.
  - `ScanView` — capture → preview (type select) → analyzing spinner → editable list → confirm; retake/discard paths; stops polling on unmount.
- Consumes: `api`, `useMealsStore`, `useScanStore`.

- [ ] **Step 1: Write the failing scan store test**

Create `resources/js/__tests__/scan.store.spec.js`:

```js
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const { apiMock } = vi.hoisted(() => ({
    apiMock: { scanMeal: vi.fn(), meal: vi.fn(), confirmMeal: vi.fn(), deleteItem: vi.fn(), updateItem: vi.fn() },
}))

vi.mock('../services/api', () => ({ api: apiMock }))

import { useScanStore } from '../stores/scan'

describe('scan store', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        setActivePinia(createPinia())
        vi.clearAllMocks()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('starts a scan and polls until ready then stops', async () => {
        apiMock.scanMeal.mockResolvedValue({ meal: { id: 1, status: 'draft' } })
        apiMock.meal
            .mockResolvedValueOnce({ meal: { id: 1, status: 'processing' } })
            .mockResolvedValueOnce({ meal: { id: 1, status: 'ready', items: [] } })

        const store = useScanStore()
        const formData = new FormData()

        await store.start(formData)

        expect(store.status).toBe('processing')
        expect(apiMock.scanMeal).toHaveBeenCalledWith(formData)

        await vi.advanceTimersByTimeAsync(2000)
        expect(store.meal.status).toBe('processing')
        expect(store.status).toBe('processing')

        await vi.advanceTimersByTimeAsync(2000)
        expect(store.status).toBe('ready')
        expect(store.timer).toBeNull()
    })

    it('marks the scan failed when the meal fails', async () => {
        apiMock.scanMeal.mockResolvedValue({ meal: { id: 2, status: 'draft' } })
        apiMock.meal.mockResolvedValue({ meal: { id: 2, status: 'failed', note: 'No food detected' } })

        const store = useScanStore()
        await store.start(new FormData())
        await vi.advanceTimersByTimeAsync(2000)

        expect(store.status).toBe('failed')
        expect(store.error).toBe('No food detected')
        expect(store.timer).toBeNull()
    })

    it('confirms the meal and resets state', async () => {
        apiMock.confirmMeal.mockResolvedValue({ meal: { id: 3, status: 'confirmed' } })
        const store = useScanStore()
        store.meal = { id: 3 }
        store.status = 'ready'

        const meal = await store.confirm()

        expect(meal.status).toBe('confirmed')
        expect(store.meal).toBeNull()
        expect(store.status).toBe('idle')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test`
Expected: FAIL — `stores/scan` module not found.

- [ ] **Step 3: Write the scan store**

Create `resources/js/stores/scan.js`:

```js
import { defineStore } from 'pinia'
import { api } from '../services/api'

const POLL_INTERVAL = 2000

export const useScanStore = defineStore('scan', {
    state: () => ({
        meal: null,
        status: 'idle', // idle | processing | ready | failed
        error: null,
        timer: null,
    }),
    actions: {
        async start(formData) {
            this.stop()
            this.status = 'processing'
            this.error = null
            const { meal } = await api.scanMeal(formData)
            this.meal = meal
            this.startPolling()
        },
        startPolling() {
            this.stop()
            this.timer = setInterval(async () => {
                await this.poll()
            }, POLL_INTERVAL)
        },
        async poll() {
            if (!this.meal) return
            const { meal } = await api.meal(this.meal.id)
            this.meal = meal
            if (meal.status === 'ready') {
                this.status = 'ready'
                this.stop()
            } else if (meal.status === 'failed') {
                this.status = 'failed'
                this.error = meal.note || 'Could not analyze this image.'
                this.stop()
            }
        },
        stop() {
            if (this.timer) {
                clearInterval(this.timer)
                this.timer = null
            }
        },
        async updateItem(id, data) {
            await api.updateItem(id, data)
            await this.poll()
        },
        async removeItem(id) {
            await api.deleteItem(id)
            await this.poll()
        },
        async confirm() {
            const { meal } = await api.confirmMeal(this.meal.id)
            this.stop()
            this.meal = null
            this.status = 'idle'
            this.error = null
            return meal
        },
    },
})
```

- [ ] **Step 4: Write the CameraCapture component**

Create `resources/js/components/CameraCapture.vue`:

```vue
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
```

- [ ] **Step 5: Write the MealItemRow component**

Create `resources/js/components/MealItemRow.vue`:

```vue
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
```

- [ ] **Step 6: Write the Scan view**

Replace `resources/js/pages/ScanView.vue`:

```vue
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
```

- [ ] **Step 7: Run tests and build**

Run: `npm test && npm run build`
Expected: 3 scan store tests PASS; build succeeds.

- [ ] **Step 8: Commit**

```bash
git add resources/js/stores/scan.js resources/js/components/CameraCapture.vue resources/js/components/MealItemRow.vue resources/js/pages/ScanView.vue resources/js/__tests__
git commit -m "feat: add photo scan flow with poll and editable results"
```

---

### Task 20: History view, weekly chart, and goals store

**Files:**
- Create: `resources/js/stores/goals.js`, `resources/js/components/WeeklyChart.vue`
- Replace: `resources/js/pages/HistoryView.vue`

**Interfaces:**
- Produces:
  - `useGoalsStore` state `goal`; actions `fetch()` → goal, `update(payload)` → goal.
  - `WeeklyChart` props `{data: [{date, calories}]}` — Chart.js bar chart of the last 7 days.
  - `HistoryView` — date picker, weekly chart (fetches `dailySummary` for the last 7 days), day's meal list via `MealCard`.
- Consumes: `api`, `MealCard`.

- [ ] **Step 1: Write the goals store**

Create `resources/js/stores/goals.js`:

```js
import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useGoalsStore = defineStore('goals', {
    state: () => ({ goal: null }),
    actions: {
        async fetch() {
            const { goal } = await api.goals()
            this.goal = goal
            return goal
        },
        async update(payload) {
            const { goal } = await api.updateGoals(payload)
            this.goal = goal
            return goal
        },
    },
})
```

- [ ] **Step 2: Write the WeeklyChart component**

Create `resources/js/components/WeeklyChart.vue`:

```vue
<template>
    <div>
        <canvas ref="canvas"></canvas>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'

const props = defineProps({
    data: { type: Array, default: () => [] },
})

const canvas = ref(null)
let chart = null

function render() {
    if (!canvas.value) return
    if (chart) chart.destroy()
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.data.map((d) => d.date.slice(5)),
            datasets: [
                {
                    label: 'Calories',
                    data: props.data.map((d) => d.calories),
                    backgroundColor: '#14b8a6',
                    borderRadius: 8,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    })
}

onMounted(() => render())
watch(() => props.data, render, { deep: true })
</script>
```

- [ ] **Step 3: Write the History view**

Replace `resources/js/pages/HistoryView.vue`:

```vue
<template>
    <div class="px-4 py-6">
        <h1 class="text-2xl font-bold text-slate-800">History</h1>

        <input type="date" v-model="date" @change="loadDay"
            class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none" />

        <div class="mt-6 rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="mb-3 font-semibold text-slate-800">Last 7 days</h2>
            <div class="relative h-56">
                <WeeklyChart :data="week" />
            </div>
        </div>

        <h2 class="mt-6 text-lg font-semibold text-slate-800">Meals · {{ date }}</h2>
        <div class="mt-3 space-y-3">
            <MealCard v-for="meal in meals" :key="meal.id" :meal="meal" />
            <p v-if="!meals.length" class="text-sm text-slate-400">No meals logged for this day.</p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import MealCard from '../components/MealCard.vue'
import WeeklyChart from '../components/WeeklyChart.vue'

const date = ref(new Date().toISOString().slice(0, 10))
const meals = ref([])
const week = ref([])

function last7Days() {
    const days = []
    const today = new Date()
    for (let i = 6; i >= 0; i--) {
        const d = new Date(today)
        d.setDate(today.getDate() - i)
        days.push(d.toISOString().slice(0, 10))
    }
    return days
}

async function loadWeek() {
    const results = await Promise.all(last7Days().map((d) => api.dailySummary(d)))
    week.value = results.map((r) => ({ date: r.date, calories: Math.round(r.totals.calories) }))
}

async function loadDay() {
    const { meals: dayMeals } = await api.mealsByDate(date.value)
    meals.value = dayMeals
}

onMounted(async () => {
    await Promise.all([loadWeek(), loadDay()])
})
</script>
```

- [ ] **Step 4: Verify build**

Run: `npm run build`
Expected: success.

- [ ] **Step 5: Commit**

```bash
git add resources/js/stores/goals.js resources/js/components/WeeklyChart.vue resources/js/pages/HistoryView.vue
git commit -m "feat: add history view, weekly chart, and goals store"
```

---

### Task 21: Profile view (goals form + logout)

**Files:**
- Replace: `resources/js/pages/ProfileView.vue`

**Interfaces:**
- Produces: `ProfileView` — user card, daily goals form (calorie_goal, protein_grams, carbs_grams, fat_grams) saved via `useGoalsStore.update`, logout button → `useAuthStore.logout()` → `login`.
- Consumes: `useAuthStore`, `useGoalsStore`.

- [ ] **Step 1: Write the Profile view**

Replace `resources/js/pages/ProfileView.vue`:

```vue
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
```

- [ ] **Step 2: Verify build**

Run: `npm run build`
Expected: success.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/ProfileView.vue
git commit -m "feat: add profile view with goals and logout"
```

---

### Task 22: PWA (service worker, manifest, icons)

**Files:**
- Modify: `vite.config.js`
- Modify: `resources/js/main.js`
- Modify: `resources/views/app.blade.php`
- Create: `scripts/generate-icons.php`
- Run: icon generation (outputs `public/icons/icon-192.png`, `public/icons/icon-512.png`)

**Interfaces:**
- Produces:
  - `vite-plugin-pwa` (generateSW, `injectRegister: null`) emitting `public/build/sw.js` and `public/build/manifest.webmanifest` on `npm run build`. `registerType: 'autoUpdate'`, offline app shell via `navigateFallback: '/'` + precached root + icons.
  - `registerSW({ immediate: true })` imported from `virtual:pwa-register` in `main.js`.
  - Manifest `<link>` + apple-touch-icon in the blade shell.
  - Installable manifest (name KaloWies, standalone, teal theme) with 192/512 icons.

- [ ] **Step 1: Write the icon generator**

Create `scripts/generate-icons.php`:

```php
<?php

$sizes = [192, 512];
$dir = __DIR__ . '/../public/icons';
$teal = [13, 148, 136];
$bg = [248, 250, 252];
$white = 255;

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    imagefill($img, 0, 0, imagecolorallocate($img, $bg[0], $bg[1], $bg[2]));

    $pad = (int) ($size * 0.08);
    $radius = (int) ($size * 0.18);
    $tealColor = imagecolorallocate($img, $teal[0], $teal[1], $teal[2]);
    filledRoundedRect($img, $pad, $pad, $size - $pad, $size - $pad, $radius, $tealColor);

    $thick = max(3, (int) ($size * 0.09));
    $whiteColor = imagecolorallocate($img, $white, $white, $white);
    imagesetthickness($img, $thick);
    $cx = (int) ($size * 0.36);
    imageline($img, $cx, (int) ($size * 0.30), $cx, (int) ($size * 0.70), $whiteColor);
    imageline($img, $cx, (int) ($size * 0.50), (int) ($size * 0.66), (int) ($size * 0.30), $whiteColor);
    imageline($img, $cx, (int) ($size * 0.50), (int) ($size * 0.66), (int) ($size * 0.70), $whiteColor);

    imagepng($img, $dir . '/icon-' . $size . '.png');
    imagedestroy($img);
}

function filledRoundedRect($img, $x1, $y1, $x2, $y2, $radius, $color)
{
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}
```

Run: `php scripts/generate-icons.php`
Verify: `public/icons/icon-192.png` and `public/icons/icon-512.png` exist.

- [ ] **Step 2: Add the PWA plugin to Vite**

Replace `vite.config.js` with:

```js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        VitePWA({
            strategies: 'generateSW',
            injectRegister: null,
            registerType: 'autoUpdate',
            manifest: {
                name: 'KaloWies',
                short_name: 'KaloWies',
                description: 'See your calories clearly.',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                theme_color: '#0d9488',
                background_color: '#f8fafc',
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                ],
            },
            workbox: {
                navigateFallback: '/',
                additionalManifestEntries: [
                    { url: '/', revision: '1' },
                    { url: '/icons/icon-192.png', revision: '1' },
                    { url: '/icons/icon-512.png', revision: '1' },
                ],
                globPatterns: ['**/*.{js,css,svg,png,ico,woff2}'],
            },
        }),
    ],
})
```

- [ ] **Step 3: Register the service worker**

Replace `resources/js/main.js` with:

```js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { registerSW } from 'virtual:pwa-register'
import App from './app.vue'
import router from './router'

registerSW({ immediate: true })

createApp(App).use(createPinia()).use(router).mount('#app')
```

- [ ] **Step 4: Link the manifest in the shell**

Replace `resources/views/app.blade.php` with:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title>KaloWies</title>
    <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/main.js'])
</head>
<body class="bg-slate-50 text-slate-800">
    <div id="app"></div>
</body>
</html>
```

- [ ] **Step 5: Verify the build emits the PWA assets**

Run: `npm run build`
Expected: success; `public/build/sw.js` and `public/build/manifest.webmanifest` exist; manifest JSON has name "KaloWies", `display: standalone`, `theme_color: #0d9488`, and both icons.

- [ ] **Step 6: Commit**

```bash
git add vite.config.js resources/js/main.js resources/views/app.blade.php scripts/generate-icons.php public/icons
git commit -m "feat: add PWA manifest, service worker, and icons"
```

---

### Task 23: Full verification and README

**Files:**
- Modify: `README.md`

**Interfaces:**
- Produces: green frontend test suite, a successful production build with PWA assets, and a runnable full-stack dev guide.

- [ ] **Step 1: Run the frontend tests**

Run: `npm test`
Expected: all Vitest specs PASS (auth store 4, MacroBar 3, scan store 3).

- [ ] **Step 2: Run the backend tests**

Run: `php artisan test`
Expected: all backend feature/unit tests PASS.

- [ ] **Step 3: Verify the production build**

Run: `npm run build`
Expected: success; `public/build/manifest.webmanifest`, `public/build/sw.js`, and hashed assets exist.

- [ ] **Step 4: Document full-stack dev setup**

Append to `README.md`:

```markdown
## Frontend setup

```bash
npm install
npm run dev        # Vite dev server (Laravel Herd serves https://kalowies.test)
```

Production assets:

```bash
npm run build
```

Frontend tests:

```bash
npm test
```

## Local verification checklist

1. `php artisan serve` or open `https://kalowies.test` (Herd) with `npm run dev` running.
2. Register a new account, then log in.
3. Tap **Take photo**, pick an image, tap **Analyze photo**.
4. Confirm the worker is running (`php artisan queue:work`) and `GEMINI_API_KEY` is set.
5. When items appear, adjust a portion, remove one, then **Log meal**.
6. Check the Today screen totals update, then view History for the 7-day chart.
7. Install the PWA: Chrome/Edge → install icon; iOS Safari → Add to Home Screen.

## Notes

- The service worker only activates after a production build (`npm run build`). During dev, PWA features are off.
- `QUEUE_CONNECTION=database` is the zero-setup fallback; use `redis` in production.
```

- [ ] **Step 5: Commit**

```bash
git add README.md
git commit -m "docs: add frontend setup and verification guide"
```
