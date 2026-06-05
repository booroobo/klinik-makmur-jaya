import { useEffect, useMemo, useState } from 'react'
import {
  ArcElement,
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'
import { Bar, Doughnut, Line } from 'react-chartjs-2'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Tooltip, Legend)

const emptyDashboard = {
  summary: {
    revenue_today: 0,
    revenue_week: 0,
    revenue_month: 0,
    orders_today: 0,
    customers: 0,
    active_medicines: 0,
    pending_prescriptions: 0,
  },
  orders_by_status: {},
  recent_orders: [],
  critical_stock_medicines: [],
  expiring_batches: [],
  top_selling_medicines: [],
  sales_daily: [],
  sales_monthly: [],
}

const orderStatusLabels = {
  pending_payment: 'Menunggu Bayar',
  paid: 'Dibayar',
  waiting_prescription_review: 'Review Resep',
  confirmed: 'Terkonfirmasi',
  processing: 'Diproses',
  ready_for_pickup: 'Siap Diambil',
  out_for_delivery: 'Dikirim',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  rejected: 'Ditolak',
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID')
}

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (context) => formatCurrency(context.parsed.y ?? context.parsed),
      },
    },
  },
  scales: {
    y: { beginAtZero: true, ticks: { callback: (value) => formatCurrency(value) } },
  },
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' },
  },
}

export default function AdminDashboard() {
  const [dashboard, setDashboard] = useState(emptyDashboard)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [expiringDays, setExpiringDays] = useState(90)

  useEffect(() => {
    const fetchDashboard = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await api.get('/admin/dashboard', {
          params: { expiring_days: expiringDays },
        })
        setDashboard(response.data.data || emptyDashboard)
      } catch (err) {
        setError(err.response?.data?.message || 'Gagal memuat dashboard.')
      } finally {
        setLoading(false)
      }
    }

    fetchDashboard()
  }, [expiringDays])

  const dailyChart = useMemo(() => ({
    labels: dashboard.sales_daily.map((row) => row.label),
    datasets: [
      {
        label: 'Omzet',
        data: dashboard.sales_daily.map((row) => row.revenue),
        borderColor: '#0f766e',
        backgroundColor: 'rgba(15, 118, 110, 0.16)',
        fill: true,
        tension: 0.35,
      },
    ],
  }), [dashboard.sales_daily])

  const monthlyChart = useMemo(() => ({
    labels: dashboard.sales_monthly.map((row) => row.label),
    datasets: [
      {
        label: 'Omzet Bulanan',
        data: dashboard.sales_monthly.map((row) => row.revenue),
        backgroundColor: '#14b8a6',
      },
    ],
  }), [dashboard.sales_monthly])

  const statusChart = useMemo(() => {
    const entries = Object.entries(dashboard.orders_by_status || {}).filter(([, value]) => Number(value) > 0)

    return {
      labels: entries.map(([status]) => orderStatusLabels[status] || status),
      datasets: [
        {
          data: entries.map(([, value]) => value),
          backgroundColor: ['#14b8a6', '#0f766e', '#f59e0b', '#3b82f6', '#8b5cf6', '#06b6d4', '#22c55e', '#64748b', '#ef4444'],
        },
      ],
    }
  }, [dashboard.orders_by_status])

  const hasStatusData = statusChart.datasets[0].data.length > 0

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="dashboard" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader
          title="Dashboard Admin"
          subtitle="Pantau ringkasan penjualan, stok, resep, dan pesanan dari data database."
        />

        <div className="flex min-w-0 flex-col gap-6 p-6 xl:p-8">
          {error && <div className="rounded-xl bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

          {loading ? (
            <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">Memuat dashboard...</div>
          ) : (
            <>
              {dashboard.critical_stock_medicines.length > 0 && (
                <div className="flex flex-col gap-4 rounded-xl bg-error-container p-4 text-on-error-container shadow-sm md:flex-row md:items-center md:justify-between">
                  <div className="flex items-center gap-4">
                    <span className="material-symbols-outlined text-error">warning</span>
                    <div>
                      <h4 className="font-bold">Peringatan: Stok Kritis Terdeteksi</h4>
                      <p className="text-sm">{dashboard.critical_stock_medicines.length} obat berada di bawah atau sama dengan batas minimum.</p>
                    </div>
                  </div>
                  <a className="rounded-lg bg-error px-4 py-2 text-center text-sm font-bold text-white" href="#critical-stock">Lihat Detail</a>
                </div>
              )}

              <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard icon="payments" label="Omzet Hari Ini" value={formatCurrency(dashboard.summary.revenue_today)} />
                <StatCard icon="calendar_month" label="Omzet Minggu Ini" value={formatCurrency(dashboard.summary.revenue_week)} />
                <StatCard icon="monitoring" label="Omzet Bulan Ini" value={formatCurrency(dashboard.summary.revenue_month)} />
                <StatCard icon="receipt_long" label="Order Hari Ini" value={dashboard.summary.orders_today} />
                <StatCard icon="description" label="Pending Resep" value={dashboard.summary.pending_prescriptions} tone={dashboard.summary.pending_prescriptions > 0 ? 'warning' : 'primary'} />
                <StatCard icon="groups" label="Pelanggan" value={dashboard.summary.customers} />
                <StatCard icon="medication" label="Obat Aktif" value={dashboard.summary.active_medicines} />
                <StatCard icon="inventory_2" label="Stok Kritis" value={dashboard.critical_stock_medicines.length} tone={dashboard.critical_stock_medicines.length > 0 ? 'danger' : 'primary'} />
                <StatCard icon="event_busy" label={`Batch Expired ${expiringDays} Hari`} value={dashboard.expiring_batches.length} tone={dashboard.expiring_batches.length > 0 ? 'warning' : 'primary'} />
                <StatCard icon="shopping_cart" label="Order Aktif" value={activeOrderCount(dashboard.orders_by_status)} />
              </div>

              <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm xl:col-span-2">
                  <div className="mb-5 flex flex-col gap-1">
                    <h4 className="font-bold">Grafik Penjualan Harian 30 Hari</h4>
                    <p className="text-sm text-on-surface-variant">Omzet dari order berbayar dan tidak dibatalkan/ditolak.</p>
                  </div>
                  <div className="h-72">
                    {dashboard.sales_daily.length > 0 ? <Line data={dailyChart} options={chartOptions} /> : <EmptyState text="Belum ada data penjualan harian." />}
                  </div>
                </section>

                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h4 className="mb-5 font-bold">Order per Status</h4>
                  <div className="h-72">
                    {hasStatusData ? <Doughnut data={statusChart} options={doughnutOptions} /> : <EmptyState text="Belum ada order." />}
                  </div>
                </section>
              </div>

              <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h4 className="mb-5 font-bold">Grafik Penjualan Bulanan</h4>
                  <div className="h-64">
                    {dashboard.sales_monthly.length > 0 ? <Bar data={monthlyChart} options={chartOptions} /> : <EmptyState text="Belum ada data bulanan." />}
                  </div>
                </section>

                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm xl:col-span-2">
                  <h4 className="mb-5 font-bold">Top Selling Medicines</h4>
                  <DataTable
                    emptyText="Belum ada obat terjual."
                    headers={['Obat', 'Terjual', 'Omzet']}
                    rows={dashboard.top_selling_medicines.map((item) => [
                      item.medicine_name,
                      item.quantity_sold,
                      formatCurrency(item.revenue),
                    ])}
                  />
                </section>
              </div>

              <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h4 className="mb-5 font-bold">Order Terbaru</h4>
                  <DataTable
                    emptyText="Belum ada order."
                    headers={['Order', 'Pelanggan', 'Status', 'Total', 'Tanggal']}
                    rows={dashboard.recent_orders.map((order) => [
                      order.order_number,
                      order.customer_name,
                      orderStatusLabels[order.status] || order.status,
                      formatCurrency(order.total),
                      formatDateTime(order.created_at),
                    ])}
                  />
                </section>

                <section id="critical-stock" className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h4 className="mb-5 font-bold">Obat Stok Kritis</h4>
                  <DataTable
                    emptyText="Tidak ada stok kritis."
                    headers={['Obat', 'Kategori', 'Stok', 'Minimum']}
                    rows={dashboard.critical_stock_medicines.map((medicine) => [
                      medicine.name,
                      medicine.category || '-',
                      medicine.total_stock,
                      medicine.minimum_stock,
                    ])}
                  />
                </section>
              </div>

              <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                <div className="mb-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                  <h4 className="font-bold">Obat Mendekati Kedaluwarsa {expiringDays} Hari</h4>
                  <label className="text-sm font-semibold text-on-surface">
                    Filter Hari:
                    <select
                      className="ml-2 rounded-lg border border-outline-variant bg-white px-3 py-1.5 font-normal outline-none focus:border-primary"
                      value={expiringDays}
                      onChange={(e) => setExpiringDays(Number(e.target.value))}
                    >
                      <option value="30">30 Hari</option>
                      <option value="60">60 Hari</option>
                      <option value="90">90 Hari</option>
                    </select>
                  </label>
                </div>
                <DataTable
                  emptyText={`Tidak ada batch yang kedaluwarsa dalam ${expiringDays} hari.`}
                  headers={['Obat', 'Batch', 'Expired', 'Sisa Hari', 'Qty', 'Bucket']}
                  rows={dashboard.expiring_batches.map((batch) => [
                    batch.medicine_name,
                    batch.batch_number,
                    batch.expired_date,
                    batch.days_remaining,
                    batch.quantity,
                    batch.bucket + ' hari',
                  ])}
                />
              </section>
            </>
          )}
        </div>
      </main>
    </div>
  )
}

function activeOrderCount(statuses) {
  return Object.entries(statuses || {})
    .filter(([status]) => !['completed', 'cancelled', 'rejected'].includes(status))
    .reduce((total, [, value]) => total + Number(value || 0), 0)
}

function StatCard({ icon, label, value, tone = 'primary' }) {
  const tones = {
    primary: 'text-primary',
    warning: 'text-amber-600',
    danger: 'text-error',
  }

  return (
    <article className="flex flex-col gap-2 rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
      <span className={'material-symbols-outlined w-fit rounded-lg bg-surface-container-high p-2 ' + tones[tone]}>{icon}</span>
      <p className="text-xs text-on-surface-variant">{label}</p>
      <h3 className="break-words text-xl font-bold">{value}</h3>
    </article>
  )
}

function DataTable({ headers, rows, emptyText }) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-left text-sm">
        <thead className="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
          <tr>
            {headers.map((header) => <th key={header} className="whitespace-nowrap px-4 py-3">{header}</th>)}
          </tr>
        </thead>
        <tbody className="divide-y divide-outline-variant">
          {rows.length === 0 ? (
            <tr><td className="px-4 py-6 text-center text-on-surface-variant" colSpan={headers.length}>{emptyText}</td></tr>
          ) : rows.map((row, index) => (
            <tr key={index}>
              {row.map((cell, cellIndex) => <td key={cellIndex} className="whitespace-nowrap px-4 py-3">{cell || '-'}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function EmptyState({ text }) {
  return (
    <div className="grid h-full place-items-center rounded-lg border border-dashed border-outline-variant text-center text-sm text-on-surface-variant">
      {text}
    </div>
  )
}
