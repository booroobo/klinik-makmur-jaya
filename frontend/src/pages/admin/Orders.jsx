import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'
import { useAuth } from '../../context/AuthContext'

const orderStatuses = ['pending_payment', 'paid', 'waiting_prescription_review', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'cancelled', 'rejected']
const paymentStatuses = ['unpaid', 'paid', 'failed', 'refunded']

const labels = {
  pending_payment: 'Menunggu Pembayaran',
  paid: 'Sudah Dibayar',
  waiting_prescription: 'Menunggu Review Resep',
  waiting_prescription_review: 'Menunggu Review Resep',
  confirmed: 'Dikonfirmasi',
  processing: 'Diproses',
  ready_for_pickup: 'Siap Diambil',
  out_for_delivery: 'Dalam Pengiriman',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  rejected: 'Ditolak',
  unpaid: 'Belum Dibayar',
  failed: 'Gagal',
  refunded: 'Refund',
  pickup: 'Ambil di Klinik',
  delivery: 'Pengiriman',
}

const label = (value) => labels[value] || value || '-'
const formatCurrency = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
const formatDateTime = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-'

export default function Orders() {
  const [searchParams] = useSearchParams()
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [orders, setOrders] = useState([])
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [filters, setFilters] = useState({ keyword: '', status: '', payment_status: '', date_from: '', date_to: '' })
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [detailLoading, setDetailLoading] = useState(false)
  const [actionLoading, setActionLoading] = useState(false)
  const [error, setError] = useState('')

  const fetchOrders = useCallback(async (page = 1, nextFilters) => {
    setLoading(true)
    setError('')
    try {
      const response = await api.get('/admin/orders', {
        params: {
          page,
          per_page: 10,
          keyword: nextFilters.keyword || undefined,
          status: nextFilters.status || undefined,
          payment_status: nextFilters.payment_status || undefined,
          date_from: nextFilters.date_from || undefined,
          date_to: nextFilters.date_to || undefined,
        },
      })
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
  }, [])

  useEffect(() => {
    const timeoutId = window.setTimeout(() => fetchOrders(1, filters), 300)
    return () => window.clearTimeout(timeoutId)
  }, [fetchOrders, filters])

  const handleFilterChange = (event) => {
    const { name, value } = event.target
    setFilters((current) => ({ ...current, [name]: value }))
  }

  const resetFilters = () => setFilters({ keyword: '', status: '', payment_status: '', date_from: '', date_to: '' })

  const openDetail = async (orderId) => {
    setDetailLoading(true)
    setError('')
    try {
      const response = await api.get('/admin/orders/' + orderId)
      setSelectedOrder(response.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat detail pesanan.')
    } finally {
      setDetailLoading(false)
    }
  }

  useEffect(() => {
    const orderId = searchParams.get('order')
    if (!orderId) return undefined
    const timeoutId = window.setTimeout(() => openDetail(orderId), 0)
    return () => window.clearTimeout(timeoutId)
  }, [searchParams])

  const refreshAfterAction = async (order) => {
    setSelectedOrder(order)
    await fetchOrders(pagination.current_page, filters)
  }

  const updatePayment = async (paymentStatus) => {
    if (!selectedOrder) return
    setActionLoading(true)
    setError('')
    try {
      const response = await api.patch('/admin/orders/' + selectedOrder.id + '/payment', { payment_status: paymentStatus })
      await refreshAfterAction(response.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memperbarui pembayaran.')
    } finally {
      setActionLoading(false)
    }
  }

  const updateStatus = async (status) => {
    if (!selectedOrder) return
    setActionLoading(true)
    setError('')
    try {
      const response = await api.patch('/admin/orders/' + selectedOrder.id + '/status', { status })
      await refreshAfterAction(response.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memperbarui status pesanan.')
    } finally {
      setActionLoading(false)
    }
  }

  const cancelOrder = async () => {
    if (!selectedOrder) return
    setActionLoading(true)
    setError('')
    try {
      const response = await api.post('/admin/orders/' + selectedOrder.id + '/cancel')
      await refreshAfterAction(response.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal membatalkan pesanan.')
    } finally {
      setActionLoading(false)
    }
  }

  const terminal = ['completed', 'cancelled', 'rejected'].includes(selectedOrder?.normalized_status || selectedOrder?.status)

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="orders" />
      <main className="ml-sidebar-width flex flex-1 flex-col">
        <AdminHeader title="Proses Pesanan" subtitle="Kelola pembayaran, status pesanan, dan proses pengambilan obat." />
        <div className="p-8">
          {error && <div className="mb-5 rounded-lg border border-error-container bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

          <section className="mb-6 rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto]">
              <label className="text-sm font-semibold text-on-surface">Keyword<input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name="keyword" value={filters.keyword} onChange={handleFilterChange} placeholder="Nomor order, pelanggan, telepon..." /></label>
              <label className="text-sm font-semibold text-on-surface">Status Order<select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="status" value={filters.status} onChange={handleFilterChange}><option value="">Semua</option>{orderStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</select></label>
              <label className="text-sm font-semibold text-on-surface">Status Bayar<select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="payment_status" value={filters.payment_status} onChange={handleFilterChange}><option value="">Semua</option>{paymentStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</select></label>
              <label className="text-sm font-semibold text-on-surface">Tanggal Mulai<input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name="date_from" type="date" value={filters.date_from} onChange={handleFilterChange} /></label>
              <label className="text-sm font-semibold text-on-surface">Tanggal Akhir<input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name="date_to" type="date" value={filters.date_to} onChange={handleFilterChange} /></label>
              <button className="mt-auto rounded-lg border border-outline-variant bg-white px-5 py-2.5 font-bold text-on-surface" type="button" onClick={resetFilters}>Reset</button>
            </div>
          </section>

          <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section className="overflow-x-auto rounded-xl border border-outline-variant bg-white shadow-sm">
              <table className="min-w-[980px] w-full text-left text-sm">
                <thead className="border-b bg-surface-container-low font-bold"><tr><th className="px-4 py-3">No Order</th><th className="px-4 py-3">Pelanggan</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3">Total</th><th className="px-4 py-3">Bayar</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Fulfillment</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody className="divide-y divide-outline-variant">
                  {loading ? (
                    <tr><td className="px-4 py-8 text-center text-on-surface-variant" colSpan="8">Memuat pesanan...</td></tr>
                  ) : orders.length === 0 ? (
                    <tr><td className="px-4 py-8 text-center text-on-surface-variant" colSpan="8">Belum ada pesanan.</td></tr>
                  ) : orders.map((order) => (
                    <tr key={order.id} className="hover:bg-surface-container-low/50">
                      <td className="px-4 py-3 font-bold text-primary">{order.order_number}</td>
                      <td className="px-4 py-3"><p className="font-bold">{order.customer_name}</p><p className="text-xs text-on-surface-variant">{order.user?.email || order.customer_phone || '-'}</p></td>
                      <td className="px-4 py-3">{formatDateTime(order.created_at)}</td>
                      <td className="px-4 py-3 font-bold">{formatCurrency(order.total)}</td>
                      <td className="px-4 py-3"><Badge value={order.payment_status} /></td>
                      <td className="px-4 py-3"><Badge value={order.normalized_status || order.status} /></td>
                      <td className="px-4 py-3">{label(order.fulfillment_method)}</td>
                      <td className="px-4 py-3 text-right"><button className="rounded-lg border border-primary px-3 py-1.5 text-sm font-bold text-primary" type="button" onClick={() => openDetail(order.id)}>Detail</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>

            <OrderDetailPanel actionLoading={actionLoading} detailLoading={detailLoading} isAdmin={isAdmin} onCancel={cancelOrder} onPayment={updatePayment} onStatus={updateStatus} order={selectedOrder} terminal={terminal} />
          </div>

          <div className="mt-5 flex items-center justify-between text-sm text-on-surface-variant">
            <p>Total data: {pagination.total}</p>
            <div className="flex gap-2">
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page <= 1} onClick={() => fetchOrders(pagination.current_page - 1, filters)}>Sebelumnya</button>
              <span className="rounded-lg bg-surface-container-low px-4 py-2">{pagination.current_page} / {pagination.last_page}</span>
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page >= pagination.last_page} onClick={() => fetchOrders(pagination.current_page + 1, filters)}>Berikutnya</button>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}

function Badge({ value }) {
  const danger = ['cancelled', 'rejected', 'failed', 'refunded'].includes(value)
  const success = ['paid', 'completed'].includes(value)
  const badgeClass = danger ? 'bg-error-container text-on-error-container' : success ? 'bg-green-100 text-green-800' : 'bg-secondary-container text-secondary'
  return <span className={'inline-flex rounded-full px-3 py-1 text-xs font-bold ' + badgeClass}>{label(value)}</span>
}

function OrderDetailPanel({ actionLoading, detailLoading, isAdmin, onCancel, onPayment, onStatus, order, terminal }) {
  if (detailLoading) return <aside className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">Memuat detail...</aside>
  if (!order) return <aside className="rounded-xl border border-outline-variant bg-white p-6 text-sm text-on-surface-variant shadow-sm">Pilih pesanan untuk melihat detail dan aksi.</aside>

  return (
    <aside className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
      <div className="-mx-6 -mt-6 mb-6 bg-primary p-6 text-white"><h3 className="text-xl font-bold">{order.order_number}</h3><p className="text-sm opacity-90">{order.customer_name}</p></div>
      <div className="mb-5 grid gap-3 text-sm">
        <Info labelText="Order Status" value={<Badge value={order.normalized_status || order.status} />} />
        <Info labelText="Payment" value={<Badge value={order.payment_status} />} />
        <Info labelText="Metode" value={order.payment_method} />
        <Info labelText="Fulfillment" value={label(order.fulfillment_method)} />
        <Info labelText="Total" value={formatCurrency(order.total)} />
        {order.customer_address && <Info labelText="Alamat" value={order.customer_address} />}
        {order.prescription && <Info labelText="Resep" value={order.prescription.status} />}
      </div>
      <h4 className="mb-3 text-xs font-bold uppercase">Item Pesanan</h4>
      <div className="mb-6 space-y-3 text-sm">
        {order.items?.map((item) => (
          <div key={item.id} className="rounded-lg bg-surface-container-low p-3">
            <div className="flex justify-between gap-3"><span className="font-bold">{item.medicine_name}{item.variant_name ? ` — ${item.variant_name}` : ''}</span><span>{formatCurrency(item.subtotal)}</span></div>
            <p className="mt-1 text-xs text-on-surface-variant">{item.quantity} x {formatCurrency(item.price)}</p>
            {item.batch_usages?.length > 0 && <div className="mt-2 text-xs text-on-surface-variant">Batch: {item.batch_usages.map((usage) => (usage.medicine_batch?.batch_number || usage.medicine_batch_id) + ' (' + usage.quantity + ')').join(', ')}</div>}
          </div>
        ))}
      </div>
      <div className="space-y-3">
        {order.payment_status !== 'paid' && !terminal && <button className="w-full rounded-lg bg-primary py-3 font-bold text-white disabled:opacity-60" type="button" disabled={actionLoading} onClick={() => onPayment('paid')}>Konfirmasi Pembayaran</button>}
        {order.allowed_next_statuses?.map((status) => <button key={status} className="w-full rounded-lg border border-primary py-3 font-bold text-primary disabled:opacity-60" type="button" disabled={actionLoading} onClick={() => onStatus(status)}>Ubah ke {label(status)}</button>)}
        {isAdmin && !terminal && <button className="w-full rounded-lg border border-error py-3 font-bold text-error disabled:opacity-60" type="button" disabled={actionLoading} onClick={onCancel}>Batalkan Pesanan</button>}
      </div>
    </aside>
  )
}

function Info({ labelText, value }) {
  return <div className="flex items-start justify-between gap-4"><span className="text-on-surface-variant">{labelText}</span><span className="text-right font-semibold">{value || '-'}</span></div>
}
