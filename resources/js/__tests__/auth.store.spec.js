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
