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
