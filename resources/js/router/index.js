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
