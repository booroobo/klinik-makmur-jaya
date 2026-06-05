import assert from 'node:assert/strict'
import test from 'node:test'
import {
  SESSION_EXPIRED_MESSAGE,
  SESSION_EXPIRED_MESSAGE_KEY,
  TOKEN_KEY,
  USER_KEY,
  handleSessionExpiredResponse,
} from './session.js'

const fakeStorage = (initial = {}) => {
  const data = new Map(Object.entries(initial))
  return {
    getItem: (key) => data.get(key) ?? null,
    removeItem: (key) => data.delete(key),
    setItem: (key, value) => data.set(key, value),
  }
}

test('session expired response clears token and user then redirects to login', () => {
  const storage = fakeStorage({ [TOKEN_KEY]: 'token', [USER_KEY]: '{"id":1}' })
  const flashStorage = fakeStorage()
  const redirects = []

  const handled = handleSessionExpiredResponse(
    { status: 401, data: { code: 'session_expired', message: 'Session expired. Please login again.' } },
    {
      storage,
      sessionStorage: flashStorage,
      location: { pathname: '/admin', assign: (path) => redirects.push(path) },
    },
  )

  assert.equal(handled, true)
  assert.equal(storage.getItem(TOKEN_KEY), null)
  assert.equal(storage.getItem(USER_KEY), null)
  assert.equal(flashStorage.getItem(SESSION_EXPIRED_MESSAGE_KEY), SESSION_EXPIRED_MESSAGE)
  assert.deepEqual(redirects, ['/login'])
})
