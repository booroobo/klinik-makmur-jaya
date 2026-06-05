import { useAuth } from '../../context/AuthContext'

export default function CashierDashboard() {
  const { user, logout } = useAuth()

  return (
    <main className="min-h-screen bg-slate-50 px-4 py-8">
      <section className="mx-auto max-w-5xl">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">
              Kasir
            </p>
            <h1 className="mt-2 text-3xl font-bold text-slate-950">
              Dashboard Kasir
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
            <h2 className="text-lg font-semibold text-slate-950">Pesanan Masuk</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder daftar pesanan pelanggan.</p>
          </article>
          <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-950">Pembayaran</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder validasi transaksi.</p>
          </article>
          <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-950">Struk</h2>
            <p className="mt-2 text-sm text-slate-600">Placeholder cetak bukti pembelian.</p>
          </article>
        </div>
      </section>
    </main>
  )
}
