/* eslint-disable react-refresh/only-export-components */
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api from '../api/axios'

const AuthContext = createContext(null)

const TOKEN_KEY = 'kmj_token'
const USER_KEY = 'kmj_user'

const readStoredUser = () => {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY))
  } catch {
    return null
  }
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(readStoredUser)
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY))
  const [loading, setLoading] = useState(Boolean(localStorage.getItem(TOKEN_KEY)))

  const clearSession = useCallback(() => {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
    delete api.defaults.headers.common.Authorization
    setToken(null)
    setUser(null)
    setLoading(false)
  }, [])

  useEffect(() => {
    const loadCurrentUser = async () => {
      if (!token) {
        setLoading(false)
        return
      }

      try {
        const response = await api.get('/me')
        const currentUser = response.data.data.user
        setUser(currentUser)
        localStorage.setItem(USER_KEY, JSON.stringify(currentUser))
      } catch {
        clearSession()
      } finally {
        setLoading(false)
      }
    }

    loadCurrentUser()
  }, [clearSession, token])

  const persistSession = useCallback(({ user: nextUser, token: nextToken }) => {
    setUser(nextUser)
    setToken(nextToken)
    api.defaults.headers.common.Authorization = `Bearer ${nextToken}`
    localStorage.setItem(USER_KEY, JSON.stringify(nextUser))
    localStorage.setItem(TOKEN_KEY, nextToken)
  }, [])

  const login = useCallback(async (credentials) => {
    const response = await api.post('/login', {
      ...credentials,
      device_name: 'web',
    })
    persistSession(response.data.data)
    return response.data.data
  }, [persistSession])

  const register = useCallback(async (payload) => {
    const response = await api.post('/register', {
      ...payload,
      device_name: 'web',
    })
    return response.data
  }, [])

  const logout = useCallback(async () => {
    try {
      if (token) {
        await api.post('/logout')
      }
    } finally {
      clearSession()
    }
  }, [clearSession, token])

  const value = useMemo(
    () => ({
      user,
      token,
      loading,
      isAuthenticated: Boolean(token && user),
      login,
      register,
      logout,
    }),
    [loading, login, logout, register, token, user],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider')
  }

  return context
}
