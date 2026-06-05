import assert from 'node:assert/strict'
import test from 'node:test'
import { validateRegistration } from './registerValidation.js'

test('registration validation returns inline field errors', () => {
  const errors = validateRegistration({
    name: 'A', email: 'invalid', phone: '12ab', address: '', password: 'short', password_confirmation: 'different',
  })

  assert.deepEqual(Object.keys(errors).sort(), ['address', 'email', 'name', 'password', 'password_confirmation', 'phone'])
})

test('valid registration data has no inline errors', () => {
  const errors = validateRegistration({
    name: 'Budi Santoso', email: 'budi@example.com', phone: '081234567890', address: 'Jl. Klinik 1', password: 'password123', password_confirmation: 'password123',
  })

  assert.deepEqual(errors, {})
})
