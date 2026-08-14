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
