import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('ibi-token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export function getErrorMessage(error) {
  return error.response?.data?.message
    || Object.values(error.response?.data?.errors || {}).flat()[0]
    || 'Tidak dapat terhubung ke server. Coba lagi.'
}

export default api
