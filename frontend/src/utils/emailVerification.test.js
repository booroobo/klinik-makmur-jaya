import assert from 'node:assert/strict'
import test from 'node:test'
import { registrationSuccessMessage, resendVerificationEmail } from './emailVerification.js'

test('verification sent message is displayed after registration', () => {
  assert.equal(registrationSuccessMessage({ message: 'Email verifikasi sudah dikirim.' }), 'Email verifikasi sudah dikirim.')
})

test('resend verification calls the correct endpoint and returns its message', async () => {
  const calls = []
  const client = {
    post: async (url, payload) => {
      calls.push([url, payload])
      return { data: { message: 'Email verifikasi baru dikirim.' } }
    },
  }

  const message = await resendVerificationEmail(client, 'user@example.com')
  assert.deepEqual(calls, [['/resend-verification-email', { email: 'user@example.com' }]])
  assert.equal(message, 'Email verifikasi baru dikirim.')
})
