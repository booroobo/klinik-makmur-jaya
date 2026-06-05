import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'
import { Line } from 'react-chartjs-2'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend)

const orderStatuses = ['pending_payment', 'paid', 'waiting_prescription_review', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'cancelled', 'rejected']

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))

const defaultFilters = {
  date_from: '',
  date_to: '',
  group_by: 'daily',
  category_id: '',
  order_status: '',
}

const buildParams = (filters) => {
  const params = {}
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '') params[key] = value
  })
  return params
}

export default function Reports() {
  const [filters, setFilters] = useState(defaultFilters)
  const [categories, setCategories] = useState([])
  const [sales, setSales] = useState(null)
  const [transactions, setTransactions] = useState([])
  const [topMedicines, setTopMedicines] = useState([])
  const [expiringMedicines, setExpiringMedicines] = useState([])
  const [reportJobs, setReportJobs] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [exporting, setExporting] = useState('')
  const [queueing, setQueueing] = useState('')
  const [queueMessage, setQueueMessage] = useState('')

  const fetchReports = useCallback(async () => {
    setLoading(true)
    setError('')
    const params = buildParams(filters)

    try {
      const [salesResponse, transactionsResponse, topResponse, expiringResponse] = await Promise.all([
        api.get('/admin/reports/sales', { params }),
        api.get('/admin/reports/transactions', { params }),
        api.get('/admin/reports/top-medicines', { params }),
        api.get('/admin/reports/expiring-medicines', { params }),
      ])

      setSales(salesResponse.data.data)
      setTransactions(transactionsResponse.data.data || [])
      setTopMedicines(topResponse.data.data || [])
      setExpiringMedicines(expiringResponse.data.data || [])
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat laporan.')
    } finally {
      setLoading(false)
    }
  }, [filters])

  const fetchReportJobs = useCallback(async () => {
    try {
      const response = await api.get('/admin/reports/queue')
      setReportJobs(response.data.data || [])
    } catch {
      setReportJobs([])
    }
  }, [])

  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const response = await api.get('/categories')
        setCategories(response.data.data || response.data || [])
      } catch {
        setCategories([])
      }
    }

    fetchCategories()
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      fetchReports()
    }, 300)

    return () => window.clearTimeout(timer)
  }, [fetchReports])

  useEffect(() => {
    const initialTimer = window.setTimeout(fetchReportJobs, 0)
    const timer = window.setInterval(fetchReportJobs, 5000)

    return () => {
      window.clearTimeout(initialTimer)
      window.clearInterval(timer)
    }
  }, [fetchReportJobs])

  const chartData = useMemo(() => ({
    labels: sales?.trend?.map((row) => row.label) || [],
    datasets: [
      {
        label: 'Omzet',
        data: sales?.trend?.map((row) => row.revenue) || [],
        borderColor: '#0f766e',
        backgroundColor: 'rgba(15, 118, 110, 0.14)',
        tension: 0.35,
      },
    ],
  }), [sales])

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: (context) => formatCurrency(context.parsed.y) } },
    },
    scales: {
      y: { beginAtZero: true, ticks: { callback: (value) => formatCurrency(value) } },
    },
  }

  const handleFilterChange = (event) => {
    const { name, value } = event.target
    setFilters((current) => ({ ...current, [name]: value }))
  }

  const downloadExport = async (type) => {
    setExporting(type)
    setError('')

    try {
      const response = await api.get('/admin/reports/sales/export/' + type, {
        params: buildParams(filters),
        responseType: 'blob',
      })
      const extension = type === 'pdf' ? 'pdf' : 'xls'
      const mime = type === 'pdf' ? 'application/pdf' : 'application/vnd.ms-excel'
      const blob = new Blob([response.data], { type: mime })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = 'laporan-penjualan.' + extension
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal mengunduh export.')
    } finally {
      setExporting('')
    }
  }

  const queueReport = async (format) => {
    setQueueing(format)
    setQueueMessage('')

    try {
      const response = await api.post('/admin/reports/queue', {
        ...buildParams(filters),
        format,
      })
      setQueueMessage(response.data.message || 'Job laporan berhasil diantrikan.')
      await fetchReportJobs()
    } catch (err) {
      setQueueMessage('')
      setError(err.response?.data?.message || 'Gagal mengantrikan laporan.')
    } finally {
      setQueueing('')
    }
  }

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="reports" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader
          title="Laporan & Analitik"
          subtitle="Pantau laporan penjualan, antrikan job besar, dan export PDF/Excel."
        />
        <div className="space-y-6 p-6 xl:p-8">
          <section className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 className="text-lg font-bold">Job Laporan Besar</h2>
                <p className="text-sm text-on-surface-variant">Laporan PDF/Excel dapat diantrikan agar proses berat berjalan di background.</p>
              </div>
              <div className="flex gap-2">
                <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 text-sm font-bold disabled:opacity-50" type="button" disabled={Boolean(queueing)} onClick={() => queueReport('pdf')}>{queueing === 'pdf' ? 'Mengantrikan...' : 'Antrikan PDF'}</button>
                <button className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white disabled:opacity-50" type="button" disabled={Boolean(queueing)} onClick={() => queueReport('excel')}>{queueing === 'excel' ? 'Mengantrikan...' : 'Antrikan Excel'}</button>
              </div>
            </div>
            {queueMessage && <div className="mt-4 rounded-lg bg-primary-container px-4 py-3 text-sm font-semibold text-on-primary-container">{queueMessage}</div>}
            <div className="mt-4 overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                  <tr>
                    <th className="whitespace-nowrap px-4 py-3">Waktu</th>
                    <th className="whitespace-nowrap px-4 py-3">Format</th>
                    <th className="whitespace-nowrap px-4 py-3">Status</th>
                    <th className="whitespace-nowrap px-4 py-3">Progress</th>
                    <th className="whitespace-nowrap px-4 py-3">File</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-outline-variant">
                  {reportJobs.length === 0 ? (
                    <tr><td className="px-4 py-6 text-center text-on-surface-variant" colSpan={5}>Belum ada job laporan.</td></tr>
                  ) : reportJobs.map((job) => (
                    <tr key={job.id}>
                      <td className="whitespace-nowrap px-4 py-3">{formatDateTime(job.created_at)}</td>
                      <td className="whitespace-nowrap px-4 py-3 font-semibold uppercase">{job.format}</td>
                      <td className="whitespace-nowrap px-4 py-3"><StatusBadge value={job.status} /></td>
                      <td className="whitespace-nowrap px-4 py-3">
                        <div className="flex min-w-40 items-center gap-3">
                          <div className="h-2 flex-1 overflow-hidden rounded-full bg-surface-container-high">
                            <div className="h-full rounded-full bg-primary" style={{ width: `${job.progress || 0}%` }} />
                          </div>
                          <span className="text-xs font-semibold">{job.progress || 0}%</span>
                        </div>
                      </td>
                      <td className="whitespace-nowrap px-4 py-3">
                        {job.download_url ? <a className="font-bold text-primary hover:underline" href={job.download_url}>Download</a> : <span className="text-on-surface-variant">Menunggu</span>}
                        {job.error_message && <p className="mt-1 text-xs text-error">{job.error_message}</p>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
              <label className="text-sm font-semibold">Tanggal Mulai<input className="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2 font-normal outline-none focus:border-primary" name="date_from" type="date" value={filters.date_from} onChange={handleFilterChange} /></label>
              <label className="text-sm font-semibold">Tanggal Akhir<input className="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2 font-normal outline-none focus:border-primary" name="date_to" type="date" value={filters.date_to} onChange={handleFilterChange} /></label>
              <label className="text-sm font-semibold">Group By<select className="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2 font-normal outline-none focus:border-primary" name="group_by" value={filters.group_by} onChange={handleFilterChange}><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
              <label className="text-sm font-semibold">Kategori<select className="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2 font-normal outline-none focus:border-primary" name="category_id" value={filters.category_id} onChange={handleFilterChange}><option value="">Semua</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
              <label className="text-sm font-semibold">Status<select className="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2 font-normal outline-none focus:border-primary" name="order_status" value={filters.order_status} onChange={handleFilterChange}><option value="">Semua</option>{orderStatuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></label>
              <div className="flex items-end gap-2">
                <button className="flex-1 rounded-lg border border-outline-variant bg-white px-4 py-2 text-sm font-bold disabled:opacity-50" type="button" disabled={Boolean(exporting)} onClick={() => downloadExport('pdf')}>{exporting === 'pdf' ? 'Export...' : 'PDF'}</button>
                <button className="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white disabled:opacity-50" type="button" disabled={Boolean(exporting)} onClick={() => downloadExport('excel')}>{exporting === 'excel' ? 'Export...' : 'Excel'}</button>
              </div>
            </div>
          </section>

          {error && <div className="rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

          {loading ? (
            <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">Memuat laporan...</div>
          ) : (
            <>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                <StatCard label="Total Omzet" value={formatCurrency(sales?.summary?.total_revenue)} />
                <StatCard label="Transaksi" value={sales?.summary?.total_transactions || 0} />
                <StatCard label="Rata-rata Order" value={formatCurrency(sales?.summary?.average_order_value)} />
                <StatCard label="Item Terjual" value={sales?.summary?.items_sold || 0} />
              </div>

              <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                <h3 className="mb-5 font-bold">Sales Trend</h3>
                <div className="h-72">
                  {(sales?.trend || []).length > 0 ? <Line data={chartData} options={chartOptions} /> : <EmptyState text="Belum ada data penjualan pada filter ini." />}
                </div>
              </section>

              <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h3 className="mb-5 font-bold">Transaksi</h3>
                  <DataTable
                    emptyText="Tidak ada transaksi."
                    headers={['Order', 'Pelanggan', 'Tanggal', 'Status', 'Payment', 'Total']}
                    rows={transactions.map((order) => [order.order_number, order.customer_name, order.date, order.status, order.payment_status, formatCurrency(order.total)])}
                  />
                </section>

                <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                  <h3 className="mb-5 font-bold">Top Medicines</h3>
                  <DataTable
                    emptyText="Belum ada obat terjual."
                    headers={['Obat', 'Kategori', 'Qty', 'Revenue']}
                    rows={topMedicines.map((medicine) => [medicine.medicine_name, medicine.category_name, medicine.quantity_sold, formatCurrency(medicine.revenue)])}
                  />
                </section>
              </div>

              <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                <h3 className="mb-5 font-bold">Obat Mendekati Kedaluwarsa</h3>
                <DataTable
                  emptyText="Tidak ada batch mendekati expired."
                  headers={['Obat', 'Kategori', 'Batch', 'Expiry', 'Qty', 'Sisa Hari']}
                  rows={expiringMedicines.map((batch) => [batch.medicine_name, batch.category_name, batch.batch_number, batch.expiry_date, batch.quantity, batch.days_remaining])}
                />
              </section>
            </>
          )}
        </div>
      </main>
    </div>
  )
}

function StatCard({ label, value }) {
  return (
    <article className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
      <p className="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{label}</p>
      <p className="mt-2 break-words text-2xl font-bold text-primary">{value}</p>
    </article>
  )
}

function DataTable({ headers, rows, emptyText }) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-left text-sm">
        <thead className="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
          <tr>{headers.map((header) => <th key={header} className="whitespace-nowrap px-4 py-3">{header}</th>)}</tr>
        </thead>
        <tbody className="divide-y divide-outline-variant">
          {rows.length === 0 ? (
            <tr><td className="px-4 py-6 text-center text-on-surface-variant" colSpan={headers.length}>{emptyText}</td></tr>
          ) : rows.map((row, index) => (
            <tr key={index}>{row.map((cell, cellIndex) => <td key={cellIndex} className="whitespace-nowrap px-4 py-3">{cell || '-'}</td>)}</tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function EmptyState({ text }) {
  return (
    <div className="grid h-full place-items-center rounded-lg border border-dashed border-outline-variant text-sm text-on-surface-variant">{text}</div>
  )
}

function StatusBadge({ value }) {
  const labelMap = {
    pending: 'Pending',
    running: 'Running',
    finished: 'Finished',
    failed: 'Failed',
  }

  const classMap = {
    pending: 'bg-outline-variant text-on-surface',
    running: 'bg-primary-container text-on-primary-container',
    finished: 'bg-green-100 text-green-800',
    failed: 'bg-error-container text-on-error-container',
  }

  const normalized = value || 'pending'

  return (
    <span className={`inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold ${classMap[normalized] || classMap.pending}`}>{labelMap[normalized] || normalized}</span>
  )
}

function formatDateTime(value) {
  if (!value) return '-'

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}
