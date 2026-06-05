import { useAuth } from '../../context/AuthContext'

export default function AdminDashboard() {
  const { user, logout } = useAuth()

  return (
    <main className="min-h-screen bg-slate-50 px-4 py-8">
      <section className="mx-auto max-w-5xl">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">
              Admin
            </p>
            <h1 className="mt-2 text-3xl font-bold text-slate-950">
              Dashboard Admin
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
            <h2 className="text-lg font-semibold text-slate-950">Manajemen Obat</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder data stok dan katalog obat.</p>
          </article>
          <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-950">Supplier</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder daftar supplier klinik.</p>
          </article>
          <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-950">Laporan</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder ringkasan transaksi dan omzet.</p>
          </article>
        </div>
      </section>
    </main>
  )
}
