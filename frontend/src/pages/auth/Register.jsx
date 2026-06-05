import { useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import api from '../../api/axios'
import Footer from '../../components/Footer'
import { useAuth } from '../../context/AuthContext'
import { registrationSuccessMessage, resendVerificationEmail } from '../../utils/emailVerification'
import { validateRegistration } from '../../utils/registerValidation'

export default function Register() {
  const { isAuthenticated, register } = useAuth()
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    address: '',
    password: '',
    password_confirmation: '',
  })
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState('')
  const [registeredEmail, setRegisteredEmail] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)

  if (isAuthenticated) {
    return <Navigate to="/catalog" replace />
  }

  const handleChange = (event) => {
    const { name, value } = event.target
    setForm((current) => ({
      ...current,
      [name]: value,
    }))
    setErrors((current) => ({ ...current, [name]: '' }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setMessage('')
    const validationErrors = validateRegistration(form)
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSubmitting(true)

    try {
      const response = await register(form)
      setRegisteredEmail(form.email.trim().toLowerCase())
      setMessage(registrationSuccessMessage(response))
    } catch (err) {
      const apiErrors = err.response?.data?.errors || {}
      setErrors(Object.fromEntries(Object.entries(apiErrors).map(([key, values]) => [key, values[0]])))
      setMessage(err.response?.data?.message || 'Registrasi gagal. Periksa data yang diisi.')
    } finally {
      setSubmitting(false)
    }
  }

  const resendVerification = async () => {
    setResending(true)
    setMessage('')
    try {
      setMessage(await resendVerificationEmail(api, registeredEmail))
    } catch (err) {
      setMessage(err.response?.data?.message || 'Gagal mengirim ulang email verifikasi.')
    } finally {
      setResending(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-gradient-to-br from-surface to-surface-container-high">
      <header className="sticky top-0 z-50 flex h-16 w-full items-center bg-white px-margin-mobile md:px-margin-desktop">
        <div className="flex items-center gap-2">
          <span className="material-symbols-outlined text-[32px] text-primary">health_and_safety</span>
          <span className="text-2xl font-bold tracking-tight text-primary">Klinik Makmur Jaya</span>
        </div>
      </header>
      <main className="flex flex-grow items-center justify-center px-margin-mobile py-12">
        <div className="grid w-full max-w-[1100px] grid-cols-1 items-center gap-12 lg:grid-cols-2">
          <div className="hidden flex-col space-y-8 lg:flex">
            <h1 className="text-5xl font-bold leading-tight text-on-surface">
              Bergabunglah dengan Layanan Kesehatan Terpercaya
            </h1>
            <p className="max-w-md text-lg text-on-surface-variant">
              Nikmati kemudahan dalam mengelola kesehatan Anda, mulai dari pemesanan obat hingga konsultasi profesional.
            </p>
            <div className="relative aspect-[4/3] overflow-hidden rounded-xl shadow-xl">
              <img
                alt="Layanan kesehatan Klinik Makmur Jaya"
                className="h-full w-full object-cover"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuAY2LDtxXlrQbIYIzK8vicdMg3_EXkhduUoAx3KAwCmVFlBQL1D8VF0up0QrGT9hlS70Ez3za4M6FFFzjSLTAJx0PRMuk6hyIaGgUHeNPz6YBXGTtlDsok-_1BFNp1QcMccGAZ2CiS4xpkVo3hTbKLMo3bu5glvhgreCrzVQn6z3ymqWE44jw7ffAgUObL7c8PZ4uz96UdCMi944At6YUQFelBGBQ5E5E9OXxGVThEpk-Yt2QwdUCM7gI0a5zZsoSzVvRjnDRtbPnk"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
              <div className="glass-card absolute bottom-6 left-6 right-6 flex items-center gap-4 rounded-lg p-4">
                <span className="material-symbols-outlined text-primary">verified</span>
                <div>
                  <p className="font-bold text-on-surface">Terverifikasi & Aman</p>
                  <p className="text-sm text-on-surface-variant">Privasi data medis Anda adalah prioritas utama kami.</p>
                </div>
              </div>
            </div>
          </div>

          <section className="glass-card w-full rounded-xl border border-outline-variant p-8 shadow-sm md:p-10">
            <h2 className="mb-2 text-3xl font-bold text-on-surface">Buat Akun Baru</h2>
            <p className="mb-8 text-on-surface-variant">Lengkapi detail di bawah ini untuk memulai.</p>
            {message && <div className={`mb-4 rounded-lg border px-4 py-3 text-sm font-semibold ${registeredEmail ? 'border-primary/20 bg-primary-container text-on-primary-container' : 'border-error-container bg-error-container text-on-error-container'}`}>{message}</div>}

            {registeredEmail ? (
              <div className="space-y-5 text-center">
                <span className="material-symbols-outlined text-6xl text-primary">mark_email_unread</span>
                <div><h3 className="text-xl font-bold">Periksa Email Anda</h3><p className="mt-2 text-sm text-on-surface-variant">Link verifikasi dikirim ke <strong>{registeredEmail}</strong>. Akun belum dapat digunakan sebelum email diverifikasi.</p></div>
                <button className="w-full rounded-lg border border-primary px-4 py-3 font-bold text-primary disabled:opacity-50" type="button" disabled={resending} onClick={resendVerification}>{resending ? 'Mengirim...' : 'Resend verification email'}</button>
                <Link className="block font-bold text-primary" to="/login">Kembali ke Login</Link>
              </div>
            ) : <form className="space-y-5" noValidate onSubmit={handleSubmit}>
              <div className="space-y-1">
                <label className="text-sm font-semibold" htmlFor="name">Nama Lengkap</label>
                <input required className="w-full rounded-lg border border-outline px-4 py-3" id="name" name="name" value={form.name} onChange={handleChange} placeholder="Contoh: Budi Santoso" />
                {errors.name && <p className="text-xs font-semibold text-error">{errors.name}</p>}
              </div>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="space-y-1">
                  <label className="text-sm font-semibold" htmlFor="email">Email</label>
                  <input required className="w-full rounded-lg border border-outline px-4 py-3" id="email" name="email" type="email" value={form.email} onChange={handleChange} placeholder="nama@email.com" />
                  {errors.email && <p className="text-xs font-semibold text-error">{errors.email}</p>}
                </div>
                <div className="space-y-1">
                  <label className="text-sm font-semibold" htmlFor="phone">Telepon</label>
                  <input required className="w-full rounded-lg border border-outline px-4 py-3" id="phone" inputMode="numeric" name="phone" value={form.phone} onChange={handleChange} placeholder="081234567890" />
                  {errors.phone && <p className="text-xs font-semibold text-error">{errors.phone}</p>}
                </div>
              </div>
              <div className="space-y-1">
                <label className="text-sm font-semibold" htmlFor="address">Alamat</label>
                <input required className="w-full rounded-lg border border-outline px-4 py-3" id="address" name="address" value={form.address} onChange={handleChange} placeholder="Alamat lengkap" />
                {errors.address && <p className="text-xs font-semibold text-error">{errors.address}</p>}
              </div>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="space-y-1">
                  <label className="text-sm font-semibold" htmlFor="password">Password</label>
                  <input required className="w-full rounded-lg border border-outline px-4 py-3" id="password" name="password" type="password" value={form.password} onChange={handleChange} />
                  {errors.password && <p className="text-xs font-semibold text-error">{errors.password}</p>}
                </div>
                <div className="space-y-1">
                  <label className="text-sm font-semibold" htmlFor="password_confirmation">Konfirmasi</label>
                  <input required className="w-full rounded-lg border border-outline px-4 py-3" id="password_confirmation" name="password_confirmation" type="password" value={form.password_confirmation} onChange={handleChange} />
                  {errors.password_confirmation && <p className="text-xs font-semibold text-error">{errors.password_confirmation}</p>}
                </div>
              </div>
              <button className="mt-4 w-full rounded-lg bg-primary py-3 font-bold text-white hover:opacity-90 disabled:opacity-50" type="submit" disabled={submitting}>
                {submitting ? 'Memproses...' : 'Daftar Sekarang'}
              </button>
              <p className="mt-4 text-center text-sm">
                Sudah punya akun? <Link to="/login" className="font-bold text-primary">Masuk di sini</Link>
              </p>
            </form>}
          </section>
        </div>
      </main>
      <Footer />
    </div>
  )
}
