import { useState } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import api from '../../api/axios'
import Footer from '../../components/Footer'
import { useAuth } from '../../context/AuthContext'
import { resendVerificationEmail } from '../../utils/emailVerification'
import { consumeSessionExpiredMessage } from '../../utils/session'

const roleRedirects = {
  pelanggan: '/catalog',
  admin: '/admin',
  apoteker: '/admin/prescription',
  kasir: '/admin/orders',
}

const demoAccounts = [
  { label: 'Login sebagai Admin Demo', email: 'admin@example.com', password: 'password' },
  { label: 'Login sebagai Apoteker Demo', email: 'apoteker@example.com', password: 'password' },
  { label: 'Login sebagai Kasir Demo', email: 'kasir@example.com', password: 'password' },
  { label: 'Login sebagai Pelanggan Demo', email: 'pelanggan@example.com', password: 'password' },
]

export default function Login() {
  const { isAuthenticated, login, user } = useAuth()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState(() => consumeSessionExpiredMessage())
  const [resendMessage, setResendMessage] = useState('')
  const [canResendVerification, setCanResendVerification] = useState(searchParams.get('verification') === 'expired')
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)
  const verificationMessage = searchParams.get('verification') === 'success'
    ? 'Email berhasil diverifikasi. Silakan login.'
    : searchParams.get('verification') === 'expired'
      ? 'Link verifikasi tidak valid atau sudah kedaluwarsa. Silakan kirim ulang email verifikasi.'
      : ''

  if (isAuthenticated) {
    const role = user?.role?.toLowerCase()
    return <Navigate to={roleRedirects[role] || '/catalog'} replace />
  }

  const handleChange = (event) => {
    setForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }))
    if (event.target.name === 'email') {
      setResendMessage('')
    }
  }

  const submitLogin = async (credentials) => {
    setError('')
    setResendMessage('')
    setCanResendVerification(false)
    setSubmitting(true)

    try {
      const session = await login(credentials)
      const role = session.user?.role?.toLowerCase()
      navigate(roleRedirects[role] || '/catalog', { replace: true })
    } catch (err) {
      if (err.response?.data?.code === 'email_not_verified') {
        setCanResendVerification(true)
      }
      setError(err.response?.data?.message || 'Login gagal. Periksa email dan password.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    await submitLogin(form)
  }

  const handleDemoLogin = async (account) => {
    setForm({ email: account.email, password: account.password })
    await submitLogin({
      email: account.email,
      password: account.password,
    })
  }

  const handleResendVerification = async () => {
    setError('')
    setResendMessage('')
    setResending(true)

    try {
      setResendMessage(await resendVerificationEmail(api, form.email))
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal mengirim ulang email verifikasi.')
    } finally {
      setResending(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <main className="relative flex flex-grow items-center justify-center overflow-hidden px-margin-mobile py-10">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_2px_2px,#bccabc_1px,transparent_0)] bg-[length:40px_40px] opacity-10"></div>
        <section className="relative z-10 w-full max-w-[440px]">
          <div className="mb-8 text-center">
            <div className="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-xl bg-primary shadow-lg">
              <span className="material-symbols-outlined text-[40px] text-white">medical_services</span>
            </div>
            <h1 className="mb-1 text-2xl font-bold text-primary">Klinik Makmur Jaya</h1>
            <p className="text-sm text-on-surface-variant">Solusi Kesehatan Terpercaya Untuk Anda</p>
          </div>

          <div className="glass-card rounded-xl border border-outline-variant p-8 shadow-sm">
            <h2 className="mb-6 text-center text-2xl font-bold">Selamat Datang</h2>
            {error && (
              <div className="mb-4 rounded-lg border border-error-container bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">
                {error}
              </div>
            )}
            {verificationMessage && !error && <div className="mb-4 rounded-lg border border-primary/20 bg-primary-container px-4 py-3 text-sm font-semibold text-on-primary-container">{verificationMessage}</div>}
            {resendMessage && <div className="mb-4 rounded-lg border border-primary/20 bg-primary-container px-4 py-3 text-sm font-semibold text-on-primary-container">{resendMessage}</div>}

            <form className="space-y-5" onSubmit={handleSubmit}>
              <div className="space-y-1">
                <label className="text-sm font-semibold" htmlFor="email">Email</label>
                <input
                  required
                  className="w-full rounded-lg border border-outline px-4 py-3"
                  id="email"
                  name="email"
                  type="email"
                  value={form.email}
                  onChange={handleChange}
                  placeholder="nama@email.com"
                />
              </div>
              <div className="space-y-1">
                <div className="flex justify-between">
                  <label className="text-sm font-semibold" htmlFor="password">Password</label>
                  <a href="#lupa-password" className="text-xs text-primary">Lupa?</a>
                </div>
                <input
                  required
                  className="w-full rounded-lg border border-outline px-4 py-3"
                  id="password"
                  name="password"
                  type="password"
                  value={form.password}
                  onChange={handleChange}
                  placeholder="Masukkan password"
                />
              </div>
              <button
                className="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-primary py-3 font-bold text-white disabled:opacity-50"
                type="submit"
                disabled={submitting}
              >
                {submitting ? 'Memproses...' : 'Masuk ke Akun'}
                <span className="material-symbols-outlined">login</span>
              </button>
              {canResendVerification && (
                <button
                  className="w-full rounded-lg border border-primary px-4 py-3 font-bold text-primary disabled:opacity-50"
                  type="button"
                  disabled={resending || !form.email.trim()}
                  onClick={handleResendVerification}
                >
                  {resending ? 'Mengirim ulang...' : 'Kirim ulang email verifikasi'}
                </button>
              )}
            </form>

            <div className="relative my-8 border-t border-outline-variant">
              <span className="absolute left-1/2 -top-3 -translate-x-1/2 bg-white px-3 text-xs text-on-surface-variant">
                Akun demo
              </span>
            </div>
            <div className="grid gap-3">
              {demoAccounts.map((account) => (
                <button
                  key={account.email}
                  className="flex w-full items-center justify-center gap-3 rounded-lg border border-outline bg-white py-3 text-sm font-bold text-on-surface transition-all hover:border-primary hover:text-primary disabled:opacity-50"
                  type="button"
                  disabled={submitting}
                  onClick={() => handleDemoLogin(account)}
                >
                  <span className="material-symbols-outlined text-primary">account_circle</span>
                  {account.label}
                </button>
              ))}
            </div>
          </div>

          <p className="mt-8 text-center text-sm">
            Belum punya akun? <Link to="/register" className="font-bold text-primary">Daftar Sekarang</Link>
          </p>
        </section>
      </main>
      <Footer />
    </div>
  )
}
