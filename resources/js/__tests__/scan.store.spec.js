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
