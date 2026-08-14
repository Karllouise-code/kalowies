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
