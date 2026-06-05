import { useAuth } from '../../context/AuthContext'

export default function PharmacistDashboard() {
  const { user, logout } = useAuth()

  return (
    <main className="min-h-screen bg-slate-50 px-4 py-8">
      <section className="mx-auto max-w-5xl">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">
              Apoteker
            </p>
            <h1 className="mt-2 text-3xl font-bold text-slate-950">
              Dashboard Apoteker
            </h1>
            <p className="mt-1 text-slate-600">Selamat datang, {user?.name}.</p>
          </div>
          <button
            className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
            type="button"
            onClick={logout}
          >
            Logout
          </button>
        </div>

        <div className="mt-8 grid gap-4 md:grid-cols-3">
          <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-950">Verifikasi Resep</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder antrean resep pelanggan.</p>
          </article>
          <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-950">Ketersediaan Obat</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder pengecekan stok obat resep.</p>
          </article>
          <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-950">Catatan Farmasi</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder catatan validasi resep.</p>
          </article>
        </div>
      </section>
    </main>
  )
}
