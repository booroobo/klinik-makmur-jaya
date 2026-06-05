import { useState } from 'react'
import Footer from '../../components/Footer'
import Navbar from '../../components/Navbar'

const contactItems = [
  { icon: 'location_on', label: 'Alamat', value: 'Jl. Sehat No. 12, Jakarta, Indonesia' },
  { icon: 'call', label: 'Telepon', value: '(021) 555-1234' },
  { icon: 'mail', label: 'Email', value: 'info@klinikmakmurjaya.co.id' },
  { icon: 'schedule', label: 'Jam operasional', value: 'Senin-Sabtu, 08.00-17.00' },
]

export default function ContactUs() {
  const [form, setForm] = useState({
    name: '',
    email: '',
    subject: '',
    message: '',
  })
  const [sent, setSent] = useState(false)

  const handleChange = (event) => {
    setForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }))
  }

  const handleSubmit = (event) => {
    event.preventDefault()
    setSent(true)
  }

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <Navbar />
      <main className="mx-auto w-full max-w-container-max flex-grow px-margin-mobile py-10 md:px-margin-desktop">
        <header className="mb-8 rounded-xl bg-primary-container px-6 py-10 text-white md:px-10">
          <p className="mb-3 text-sm font-bold uppercase tracking-wider">Klinik Makmur Jaya</p>
          <h1 className="text-4xl font-bold">Hubungi Kami</h1>
          <p className="mt-3 max-w-2xl text-white/90">
            Kami siap membantu kebutuhan obat, konsultasi farmasi, dan informasi layanan pelanggan.
          </p>
        </header>

        <div className="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
          <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
            <h2 className="mb-5 text-xl font-bold text-on-surface">Informasi Kontak</h2>
            <div className="space-y-4">
              {contactItems.map((item) => (
                <div key={item.label} className="flex gap-4 rounded-lg bg-surface-container-low p-4">
                  <span className="material-symbols-outlined text-primary">{item.icon}</span>
                  <div>
                    <p className="text-sm font-bold text-on-surface">{item.label}</p>
                    <p className="mt-1 text-sm leading-6 text-on-surface-variant">{item.value}</p>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
            <h2 className="mb-5 text-xl font-bold text-on-surface">Kirim Pesan</h2>
            {sent && (
              <div className="mb-5 rounded-lg border border-secondary-container bg-secondary-container/40 px-4 py-3 text-sm font-semibold text-secondary">
                Pesan berhasil disiapkan. Backend pengiriman akan diintegrasikan pada tahap berikutnya.
              </div>
            )}
            <form className="grid gap-4" onSubmit={handleSubmit}>
              <div className="grid gap-4 md:grid-cols-2">
                <label className="text-sm font-semibold text-on-surface" htmlFor="name">
                  Nama lengkap
                  <input
                    required
                    className="mt-1 w-full rounded-lg border border-outline px-4 py-3 font-normal"
                    id="name"
                    name="name"
                    value={form.name}
                    onChange={handleChange}
                    placeholder="Nama Anda"
                  />
                </label>
                <label className="text-sm font-semibold text-on-surface" htmlFor="email">
                  Email
                  <input
                    required
                    className="mt-1 w-full rounded-lg border border-outline px-4 py-3 font-normal"
                    id="email"
                    name="email"
                    type="email"
                    value={form.email}
                    onChange={handleChange}
                    placeholder="nama@email.com"
                  />
                </label>
              </div>
              <label className="text-sm font-semibold text-on-surface" htmlFor="subject">
                Subjek
                <input
                  required
                  className="mt-1 w-full rounded-lg border border-outline px-4 py-3 font-normal"
                  id="subject"
                  name="subject"
                  value={form.subject}
                  onChange={handleChange}
                  placeholder="Subjek pesan"
                />
              </label>
              <label className="text-sm font-semibold text-on-surface" htmlFor="message">
                Pesan
                <textarea
                  required
                  className="mt-1 min-h-36 w-full rounded-lg border border-outline px-4 py-3 font-normal"
                  id="message"
                  name="message"
                  value={form.message}
                  onChange={handleChange}
                  placeholder="Tuliskan pesan Anda"
                />
              </label>
              <button className="w-full rounded-lg bg-primary py-3 font-bold text-white shadow-sm hover:opacity-90 md:w-fit md:px-8" type="submit">
                Kirim Pesan
              </button>
            </form>
          </section>
        </div>
      </main>
      <Footer />
    </div>
  )
}
