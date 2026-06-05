import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import Footer from '../../components/Footer'
import Navbar from '../../components/Navbar'

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))

const statusLabels = {
  waiting_prescription: 'Menunggu Review Resep',
  waiting_prescription_review: 'Menunggu Review Resep',
  pending_payment: 'Menunggu Pembayaran',
  paid: 'Sudah Dibayar',
  processing: 'Diproses',
  confirmed: 'Dikonfirmasi',
  ready_for_pickup: 'Siap Diambil',
  out_for_delivery: 'Dalam Pengiriman',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  rejected: 'Ditolak',
}

export default function MyOrders() {
  const [orders, setOrders] = useState([])
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchOrders = async (page = 1) => {
    setLoading(true)
    setError('')

    try {
      const response = await api.get('/my-orders', { params: { page, per_page: 10 } })
      setOrders(response.data.data || [])
      setPagination({
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
      })
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat pesanan.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    // Riwayat pesanan dimuat dari API saat halaman pertama kali dibuka.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchOrders()
  }, [])

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <Navbar />
      <main className="mx-auto w-full max-w-container-max flex-grow px-margin-mobile py-8 md:px-margin-desktop">
        <header className="mb-8">
          <h1 className="text-4xl font-bold">Pesanan Saya</h1>
          <p className="mt-2 text-on-surface-variant">Pantau status pesanan, pembayaran, dan verifikasi resep.</p>
        </header>

        {error && <div className="mb-5 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

        <section className="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
          <div className="border-b border-outline-variant bg-surface-container-low px-6 py-4 font-bold">
            Riwayat Pesanan
          </div>
          {loading ? (
            <div className="p-8 text-center text-on-surface-variant">Memuat pesanan...</div>
          ) : orders.length === 0 ? (
            <div className="p-8 text-center">
              <p className="text-on-surface-variant">Belum ada pesanan.</p>
              <Link className="mt-4 inline-flex rounded-lg bg-primary px-5 py-2.5 font-bold text-white" to="/catalog">Belanja Sekarang</Link>
            </div>
          ) : (
            <div className="divide-y divide-outline-variant">
              {orders.map((order) => (
                <article key={order.id} className="flex flex-col gap-4 p-6 md:flex-row md:items-center md:justify-between">
                  <div>
                    <p className="text-lg font-bold text-on-surface">{order.order_number}</p>
                    <p className="mt-1 text-sm text-on-surface-variant">
                      {new Date(order.created_at).toLocaleDateString('id-ID')} • {order.items_count} item
                    </p>
                    <span className="mt-3 inline-flex rounded-full bg-secondary-container px-3 py-1 text-xs font-bold text-secondary">
                      {statusLabels[order.status] || order.status}
                    </span>
                  </div>
                  <div className="text-left md:text-right">
                    <p className="text-2xl font-bold text-primary">{formatCurrency(order.total)}</p>
                    <Link className="mt-3 inline-flex rounded-lg border border-primary px-4 py-2 text-sm font-bold text-primary hover:bg-surface-container-low" to={`/my-orders/${order.id}`}>
                      Detail
                    </Link>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>

        {pagination.last_page > 1 && (
          <div className="mt-5 flex items-center justify-end gap-2 text-sm">
            <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page <= 1} onClick={() => fetchOrders(pagination.current_page - 1)}>
              Sebelumnya
            </button>
            <span className="rounded-lg bg-surface-container-low px-4 py-2">{pagination.current_page} / {pagination.last_page}</span>
            <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page >= pagination.last_page} onClick={() => fetchOrders(pagination.current_page + 1)}>
              Berikutnya
            </button>
          </div>
        )}
      </main>
      <Footer />
    </div>
  )
}
