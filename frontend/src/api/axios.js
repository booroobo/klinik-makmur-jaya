import axios from 'axios'
import { handleSessionExpiredResponse } from '../utils/session'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('kmj_token')

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (handleSessionExpiredResponse(error.response)) {
      delete api.defaults.headers.common.Authorization
    }
    return Promise.reject(error)
  },
)

export default api
