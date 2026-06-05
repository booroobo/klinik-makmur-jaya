import Footer from '../../components/Footer'
import Navbar from '../../components/Navbar'

const values = [
  {
    icon: 'workspace_premium',
    title: 'Profesional',
    description: 'Tim apoteker bersertifikasi siap membantu kebutuhan kesehatan Anda.',
  },
  {
    icon: 'verified_user',
    title: 'Terpercaya',
    description: 'Produk resmi, aman, dan melalui proses kontrol kualitas yang ketat.',
  },
  {
    icon: 'local_shipping',
    title: 'Cepat & Efisien',
    description: 'Pengiriman tepat waktu dan layanan mudah diakses dari mana saja.',
  },
]

export default function AboutUs() {
  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <Navbar />
      <main className="mx-auto w-full max-w-container-max flex-grow px-margin-mobile py-10 md:px-margin-desktop">
        <section className="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
          <div className="bg-primary-container px-6 py-12 text-white md:px-10">
            <p className="mb-3 text-sm font-bold uppercase tracking-wider">Klinik Makmur Jaya</p>
            <h1 className="max-w-3xl text-4xl font-bold leading-tight">
              Tentang Klinik Makmur Jaya
            </h1>
          </div>
          <div className="grid gap-8 p-6 md:p-10 lg:grid-cols-[1.2fr_0.8fr]">
            <div>
              <h2 className="mb-4 text-2xl font-bold text-on-surface">Solusi kesehatan terpercaya untuk masyarakat</h2>
              <p className="text-base leading-8 text-on-surface-variant">
                Klinik Makmur Jaya adalah apotek modern yang menyediakan obat-obatan berkualitas,
                suplemen, dan layanan konsultasi farmasi profesional untuk masyarakat. Kami
                berkomitmen memberikan solusi kesehatan terpercaya dengan pelayanan cepat dan ramah.
              </p>
            </div>
            <div className="rounded-xl border border-outline-variant bg-surface-container-low p-6">
              <span className="material-symbols-outlined mb-4 text-5xl text-primary">health_and_safety</span>
              <h3 className="mb-2 text-xl font-bold text-on-surface">Layanan farmasi modern</h3>
              <p className="text-sm leading-6 text-on-surface-variant">
                Kami menggabungkan pelayanan apotek, edukasi kesehatan, dan kemudahan transaksi digital
                dalam satu pengalaman yang nyaman.
              </p>
            </div>
          </div>
        </section>

        <section className="mt-8">
          <h2 className="mb-5 text-2xl font-bold text-on-surface">Nilai-nilai Kami</h2>
          <div className="grid gap-5 md:grid-cols-3">
            {values.map((value) => (
              <article key={value.title} className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                <span className="material-symbols-outlined mb-4 rounded-lg bg-secondary-container p-3 text-3xl text-secondary">
                  {value.icon}
                </span>
                <h3 className="mb-2 text-lg font-bold text-on-surface">{value.title}</h3>
                <p className="text-sm leading-6 text-on-surface-variant">{value.description}</p>
              </article>
            ))}
          </div>
        </section>
      </main>
      <Footer />
    </div>
  )
}
