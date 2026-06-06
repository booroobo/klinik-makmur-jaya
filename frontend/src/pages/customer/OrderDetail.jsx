import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
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
  confirmed: 'Dikonfirmasi',
  processing: 'Diproses',
  ready_for_pickup: 'Siap Diambil',
  out_for_delivery: 'Dalam Pengiriman',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  rejected: 'Ditolak',
}

const prescriptionLabels = {
  pending: 'Menunggu Review',
  approved: 'Disetujui',
  rejected: 'Ditolak',
}

const fulfillmentLabels = {
  pickup: 'Ambil di Klinik',
  delivery: 'Pengiriman',
}

const paymentLabels = {
  bank_transfer: 'Transfer Bank',
  cashier: 'Bayar di Kasir',
  e_wallet: 'E-Wallet',
}

export default function OrderDetail() {
  const { id } = useParams()
  const location = useLocation()
  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const fetchOrder = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await api.get(`/my-orders/${id}`)
        setOrder(response.data.data)
      } catch (err) {
        setError(err.response?.data?.message || 'Gagal memuat detail pesanan.')
      } finally {
        setLoading(false)
      }
    }

    fetchOrder()
  }, [id])

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <Navbar />
      <main className="mx-auto w-full max-w-container-max flex-grow px-margin-mobile py-8 md:px-margin-desktop">
        <Link to="/my-orders" className="mb-6 inline-flex items-center gap-2 text-sm font-bold text-primary">
          <span className="material-symbols-outlined text-[18px]">arrow_back</span>
          Kembali ke pesanan
        </Link>

        {location.state?.message && (
          <div className="mb-5 rounded-lg border border-secondary-container bg-secondary-container px-4 py-3 text-sm font-semibold text-secondary">
            {location.state.message}
          </div>
        )}
        {error && <div className="mb-5 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

        {loading ? (
          <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">Memuat detail pesanan...</div>
        ) : order && (
          <>
            <header className="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
              <div>
                <p className="text-sm font-bold uppercase tracking-wider text-primary">Detail Pesanan</p>
                <h1 className="mt-2 text-4xl font-bold">{order.order_number}</h1>
                <p className="mt-2 text-on-surface-variant">{new Date(order.created_at).toLocaleString('id-ID')}</p>
              </div>
              <span className="w-fit rounded-full bg-secondary-container px-4 py-2 text-sm font-bold text-secondary">
                {statusLabels[order.status] || order.status}
              </span>
            </header>

            <div className="grid items-start gap-gutter lg:grid-cols-12">
              <section className="space-y-6 lg:col-span-8">
                <div className="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                  <div className="border-b border-outline-variant bg-surface-container-low px-6 py-4 font-bold">Item Pesanan</div>
                  <div className="divide-y divide-outline-variant">
                    {order.items.map((item) => (
                      <article key={item.id} className="flex justify-between gap-4 p-6">
                        <div>
                          <p className="font-bold">{item.medicine_name}{item.variant_name ? ` — ${item.variant_name}` : ''}</p>
                          <p className="mt-1 text-sm text-on-surface-variant">{item.quantity} x {formatCurrency(item.price)}</p>
                          {item.requires_prescription && <span className="mt-2 inline-flex rounded-full bg-error-container px-2 py-0.5 text-[10px] font-bold text-on-error-container">BUTUH RESEP</span>}
                        </div>
                        <span className="font-bold text-primary">{formatCurrency(item.subtotal)}</span>
                      </article>
                    ))}
                  </div>
                </div>

                {order.prescription && (
                  <div className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-xl font-bold">Status Resep</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                      <InfoCard label="Status" value={prescriptionLabels[order.prescription.status] || order.prescription.status} />
                      <InfoCard label="Catatan Apoteker" value={order.prescription.pharmacist_notes || '-'} />
                    </div>
                  </div>
                )}
              </section>

              <aside className="space-y-6 lg:col-span-4">
                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h2 className="mb-5 text-xl font-bold">Ringkasan Pembayaran</h2>
                  <div className="space-y-3 text-sm">
                    <SummaryRow label="Subtotal" value={formatCurrency(order.subtotal)} />
                    <SummaryRow label="Biaya Layanan" value={formatCurrency(order.service_fee)} />
                    <SummaryRow label="Biaya Pengiriman" value={formatCurrency(order.delivery_fee)} />
                    <SummaryRow label="Metode Pembayaran" value={paymentLabels[order.payment_method] || order.payment_method} />
                    <SummaryRow label="Status Pembayaran" value={order.payment_status === 'paid' ? 'Sudah Dibayar' : 'Belum Dibayar'} />
                  </div>
                  <div className="mt-5 flex items-end justify-between border-t border-outline-variant pt-4">
                    <span className="font-bold">Total</span>
                    <span className="text-3xl font-bold text-primary">{formatCurrency(order.total)}</span>
                  </div>
                </section>

                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h2 className="mb-5 text-xl font-bold">Informasi Pelanggan</h2>
                  <div className="space-y-3 text-sm">
                    <SummaryRow label="Nama" value={order.customer_name} />
                    <SummaryRow label="Telepon" value={order.customer_phone || '-'} />
                    <SummaryRow label="Pengambilan" value={fulfillmentLabels[order.fulfillment_method] || order.fulfillment_method} />
                    <div>
                      <p className="font-bold text-on-surface">Alamat</p>
                      <p className="mt-1 text-on-surface-variant">{order.customer_address || '-'}</p>
                    </div>
                    <div>
                      <p className="font-bold text-on-surface">Catatan</p>
                      <p className="mt-1 text-on-surface-variant">{order.notes || '-'}</p>
                    </div>
                  </div>
                </section>
              </aside>
            </div>
          </>
        )}
      </main>
      <Footer />
    </div>
  )
}

function InfoCard({ label, value }) {
  return (
    <div className="rounded-lg border border-outline-variant p-4">
      <p className="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{label}</p>
      <p className="mt-1 text-sm font-semibold text-on-surface">{value}</p>
    </div>
  )
}

function SummaryRow({ label, value }) {
  return (
    <div className="flex justify-between gap-4 text-on-surface-variant">
      <span>{label}</span>
      <span className="text-right font-semibold text-on-surface">{value}</span>
    </div>
  )
}
