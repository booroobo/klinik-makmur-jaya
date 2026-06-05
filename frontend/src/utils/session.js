export const TOKEN_KEY = 'kmj_token'
export const USER_KEY = 'kmj_user'
export const SESSION_EXPIRED_MESSAGE_KEY = 'kmj_session_expired_message'
export const SESSION_EXPIRED_MESSAGE = 'Sesi Anda telah berakhir. Silakan login kembali.'

export const isSessionExpiredResponse = (response) => (
  response?.status === 401
  && (response?.data?.code === 'session_expired' || response?.data?.message === 'Session expired. Please login again.')
)

export const clearStoredSession = (storage = globalThis.localStorage) => {
  storage?.removeItem(TOKEN_KEY)
  storage?.removeItem(USER_KEY)
}

export const handleSessionExpiredResponse = (response, options = {}) => {
  if (!isSessionExpiredResponse(response)) return false

  const storage = options.storage ?? globalThis.localStorage
  const sessionStorage = options.sessionStorage ?? globalThis.sessionStorage
  const location = options.location ?? globalThis.location

  clearStoredSession(storage)
  sessionStorage?.setItem(SESSION_EXPIRED_MESSAGE_KEY, SESSION_EXPIRED_MESSAGE)

  if (location && location.pathname !== '/login') {
    location.assign('/login')
  }

  return true
}

export const consumeSessionExpiredMessage = (storage = globalThis.sessionStorage) => {
  const message = storage?.getItem(SESSION_EXPIRED_MESSAGE_KEY) || ''
  storage?.removeItem(SESSION_EXPIRED_MESSAGE_KEY)
  return message
}
