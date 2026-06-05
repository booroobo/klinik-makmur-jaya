import { useCallback, useEffect, useMemo, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'

const statusOptions = ['', 'pending', 'approved', 'rejected']

const statusLabels = {
  pending: 'Menunggu',
  approved: 'Disetujui',
  rejected: 'Ditolak',
}

const orderStatusLabels = {
  waiting_prescription: 'Menunggu Review Resep',
  waiting_prescription_review: 'Menunggu Review Resep',
  pending_payment: 'Menunggu Pembayaran',
  paid: 'Sudah Dibayar',
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

const buildParams = (filters, page) => {
  const params = { page, per_page: 10 }

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '') {
      params[key] = value
    }
  })

  return params
}

const fileUrl = (url) => {
  if (!url) return ''
  if (url.startsWith('http')) return url

  const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
  return apiBase.replace(/\/api\/?$/, '') + url
}

export default function PrescriptionVerification() {
  const [filters, setFilters] = useState({
    keyword: '',
    status: '',
    date_from: '',
    date_to: '',
  })
  const [prescriptions, setPrescriptions] = useState([])
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [selectedId, setSelectedId] = useState(null)
  const [selected, setSelected] = useState(null)
  const [notes, setNotes] = useState('')
  const [reason, setReason] = useState('')
  const [loading, setLoading] = useState(true)
  const [detailLoading, setDetailLoading] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [detailError, setDetailError] = useState('')
  const [message, setMessage] = useState('')

  const selectedFileUrl = useMemo(() => fileUrl(selected?.file_url || ''), [selected])

  const fetchPrescriptions = useCallback(async (page = 1) => {
    setLoading(true)
    setError('')

    try {
      const response = await api.get('/admin/prescriptions', {
        params: buildParams(filters, page),
      })
      const rows = response.data.data || []
      setPrescriptions(rows)
      setPagination({
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
      })

      if (!selectedId && rows.length > 0) {
        setSelectedId(rows[0].id)
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat daftar resep.')
    } finally {
      setLoading(false)
    }
  }, [filters, selectedId])

  const fetchDetail = useCallback(async (id) => {
    if (!id) {
      setSelected(null)
      return
    }

    setDetailLoading(true)
    setDetailError('')

    try {
      const response = await api.get('/admin/prescriptions/' + id)
      setSelected(response.data.data)
      setNotes(response.data.data.pharmacist_notes || '')
      setReason('')
    } catch (err) {
      setDetailError(err.response?.data?.message || 'Gagal memuat detail resep.')
    } finally {
      setDetailLoading(false)
    }
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      fetchPrescriptions(1)
    }, 250)

    return () => window.clearTimeout(timer)
  }, [fetchPrescriptions])

  useEffect(() => {
    // Detail resep dimuat ulang setiap pilihan baris berubah.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchDetail(selectedId)
  }, [fetchDetail, selectedId])

  const handleFilterChange = (event) => {
    const { name, value } = event.target
    setSelectedId(null)
    setSelected(null)
    setFilters((current) => ({ ...current, [name]: value }))
  }

  const handlePageChange = (page) => {
    fetchPrescriptions(page)
  }

  const review = async (action) => {
    if (!selected) return
    if (action === 'reject' && !reason.trim()) {
      setDetailError('Alasan penolakan wajib diisi.')
      return
    }

    setSubmitting(true)
    setDetailError('')
    setMessage('')

    try {
      const payload = action === 'approve' ? { notes } : { reason }
      const response = await api.patch('/admin/prescriptions/' + selected.id + '/' + action, payload)
      setSelected(response.data.data)
      setNotes(response.data.data.pharmacist_notes || '')
      setReason('')
      setMessage(response.data.message || 'Review resep berhasil disimpan.')
      fetchPrescriptions(pagination.current_page)
    } catch (err) {
      setDetailError(err.response?.data?.message || 'Gagal menyimpan review resep.')
    } finally {
      setSubmitting(false)
    }
  }

  const finalStatus = selected && selected.status !== 'pending'

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="prescription" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader
          title="Verifikasi Resep"
          subtitle="Tinjau, setujui, atau tolak resep yang dikirim pelanggan."
        />

        <div className="grid min-w-0 grid-cols-1 gap-6 p-6 xl:grid-cols-12 xl:p-8">
          <section className="min-w-0 rounded-xl border border-outline-variant bg-white shadow-sm xl:col-span-7">
            <div className="border-b border-outline-variant bg-surface-container-low p-4">
              <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                  <h2 className="font-bold">Antrean Resep</h2>
                  <p className="text-sm text-on-surface-variant">{pagination.total} resep ditemukan</p>
                </div>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:w-[560px]">
                  <input
                    className="rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm outline-none focus:border-primary"
                    name="keyword"
                    placeholder="Cari order, pelanggan, obat..."
                    type="search"
                    value={filters.keyword}
                    onChange={handleFilterChange}
                  />
                  <select
                    className="rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm outline-none focus:border-primary"
                    name="status"
                    value={filters.status}
                    onChange={handleFilterChange}
                  >
                    {statusOptions.map((status) => (
                      <option key={status || 'all'} value={status}>{status ? statusLabels[status] : 'Semua status'}</option>
                    ))}
                  </select>
                  <input
                    className="rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm outline-none focus:border-primary"
                    name="date_from"
                    type="date"
                    value={filters.date_from}
                    onChange={handleFilterChange}
                  />
                  <input
                    className="rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm outline-none focus:border-primary"
                    name="date_to"
                    type="date"
                    value={filters.date_to}
                    onChange={handleFilterChange}
                  />
                </div>
              </div>
            </div>

            {error && <div className="m-4 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

            <div className="overflow-x-auto">
              <table className="min-w-[760px] w-full text-left text-sm">
                <thead className="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                  <tr>
                    <th className="px-4 py-3">No Pesanan</th>
                    <th className="px-4 py-3">Pelanggan</th>
                    <th className="px-4 py-3">Tanggal</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Reviewer</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-outline-variant">
                  {loading ? (
                    <tr><td className="px-4 py-8 text-center text-on-surface-variant" colSpan="5">Memuat resep...</td></tr>
                  ) : prescriptions.length === 0 ? (
                    <tr><td className="px-4 py-8 text-center text-on-surface-variant" colSpan="5">Belum ada resep sesuai filter.</td></tr>
                  ) : prescriptions.map((prescription) => (
                    <tr
                      key={prescription.id}
                      className={(selectedId === prescription.id ? 'bg-secondary-container/20 ' : '') + 'cursor-pointer hover:bg-surface-container-low'}
                      onClick={() => setSelectedId(prescription.id)}
                    >
                      <td className="px-4 py-4 font-bold text-primary">{prescription.order?.order_number || '-'}</td>
                      <td className="px-4 py-4">
                        <p className="font-semibold text-on-surface">{prescription.order?.customer_name || prescription.user?.name || '-'}</p>
                        <p className="text-xs text-on-surface-variant">{prescription.user?.email || '-'}</p>
                      </td>
                      <td className="px-4 py-4 text-on-surface-variant">{formatDateTime(prescription.created_at)}</td>
                      <td className="px-4 py-4"><StatusBadge status={prescription.status} /></td>
                      <td className="px-4 py-4 text-on-surface-variant">{prescription.reviewed_by?.name || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {pagination.last_page > 1 && (
              <div className="flex items-center justify-end gap-2 border-t border-outline-variant p-4 text-sm">
                <button className="rounded-lg border border-outline-variant px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page <= 1 || loading} onClick={() => handlePageChange(pagination.current_page - 1)}>
                  Sebelumnya
                </button>
                <span className="rounded-lg bg-surface-container-low px-4 py-2">{pagination.current_page} / {pagination.last_page}</span>
                <button className="rounded-lg border border-outline-variant px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page >= pagination.last_page || loading} onClick={() => handlePageChange(pagination.current_page + 1)}>
                  Berikutnya
                </button>
              </div>
            )}
          </section>

          <section className="min-w-0 space-y-6 xl:col-span-5">
            <div className="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
              <div className="border-b border-outline-variant bg-surface-container-low p-4 font-bold">Detail Resep</div>

              {detailLoading ? (
                <div className="p-8 text-center text-on-surface-variant">Memuat detail resep...</div>
              ) : !selected ? (
                <div className="p-8 text-center text-on-surface-variant">Pilih salah satu resep untuk melihat detail.</div>
              ) : (
                <div className="space-y-5 p-5">
                  {message && <div className="rounded-lg bg-secondary-container px-4 py-3 text-sm font-semibold text-secondary">{message}</div>}
                  {detailError && <div className="rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{detailError}</div>}

                  <div className="grid gap-3 sm:grid-cols-2">
                    <Info label="Order" value={selected.order?.order_number || '-'} />
                    <Info label="Status Resep" value={statusLabels[selected.status] || selected.status} />
                    <Info label="Pelanggan" value={selected.order?.customer_name || selected.user?.name || '-'} />
                    <Info label="Status Order" value={orderStatusLabels[selected.order?.status] || selected.order?.status || '-'} />
                    <Info label="Reviewer" value={selected.reviewed_by?.name || '-'} />
                    <Info label="Direview Pada" value={formatDateTime(selected.reviewed_at)} />
                  </div>

                  <div>
                    <p className="mb-2 text-sm font-bold">File Resep</p>
                    {selectedFileUrl ? (
                      <div className="overflow-hidden rounded-lg border border-outline-variant">
                        <iframe className="h-72 w-full bg-surface-container-low" src={selectedFileUrl} title="Preview resep" />
                        <a className="flex items-center justify-center gap-2 border-t border-outline-variant px-4 py-3 text-sm font-bold text-primary" href={selectedFileUrl} rel="noreferrer" target="_blank">
                          <span className="material-symbols-outlined text-[18px]">open_in_new</span>
                          Buka file resep
                        </a>
                      </div>
                    ) : (
                      <div className="rounded-lg border border-dashed border-outline-variant p-6 text-center text-on-surface-variant">File resep tidak tersedia.</div>
                    )}
                  </div>

                  <div>
                    <p className="mb-2 text-sm font-bold">Obat Resep</p>
                    <div className="max-h-52 overflow-auto rounded-lg border border-outline-variant">
                      {(selected.order?.items || []).filter((item) => item.requires_prescription).map((item) => (
                        <div key={item.id} className="flex justify-between gap-4 border-b border-outline-variant px-4 py-3 last:border-b-0">
                          <div>
                            <p className="font-semibold">{item.medicine_name}</p>
                            <p className="text-xs text-on-surface-variant">{item.quantity} x {formatCurrency(item.price)}</p>
                          </div>
                          <p className="font-bold text-primary">{formatCurrency(item.subtotal)}</p>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="space-y-3 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                    <label className="block text-sm font-bold" htmlFor="approval_notes">Catatan approve</label>
                    <textarea
                      className="min-h-24 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm outline-none focus:border-primary disabled:bg-surface-container-low"
                      disabled={finalStatus || submitting}
                      id="approval_notes"
                      placeholder="Catatan opsional untuk pelanggan"
                      value={notes}
                      onChange={(event) => setNotes(event.target.value)}
                    />

                    <label className="block text-sm font-bold" htmlFor="reject_reason">Alasan penolakan</label>
                    <textarea
                      className="min-h-24 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm outline-none focus:border-primary disabled:bg-surface-container-low"
                      disabled={finalStatus || submitting}
                      id="reject_reason"
                      placeholder="Wajib diisi saat menolak resep"
                      value={reason}
                      onChange={(event) => setReason(event.target.value)}
                    />

                    <div className="flex flex-col gap-3 sm:flex-row">
                      <button className="flex-1 rounded-lg border border-error px-4 py-3 font-bold text-error disabled:opacity-40" type="button" disabled={finalStatus || submitting} onClick={() => review('reject')}>
                        Tolak Resep
                      </button>
                      <button className="flex-[2] rounded-lg bg-primary px-4 py-3 font-bold text-white disabled:opacity-40" type="button" disabled={finalStatus || submitting} onClick={() => review('approve')}>
                        Setujui Resep
                      </button>
                    </div>

                    {finalStatus && <p className="text-xs font-semibold text-on-surface-variant">Resep final tidak bisa diproses ulang.</p>}
                  </div>
                </div>
              )}
            </div>
          </section>
        </div>
      </main>
    </div>
  )
}

function StatusBadge({ status }) {
  const classes = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-secondary-container text-secondary',
    rejected: 'bg-error-container text-on-error-container',
  }

  return (
    <span className={'whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold ' + (classes[status] || 'bg-surface-container-low text-on-surface-variant')}>
      {statusLabels[status] || status}
    </span>
  )
}

function Info({ label, value }) {
  return (
    <div className="rounded-lg border border-outline-variant p-3">
      <p className="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">{label}</p>
      <p className="mt-1 break-words text-sm font-semibold text-on-surface">{value}</p>
    </div>
  )
}
