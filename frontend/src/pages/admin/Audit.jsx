import { useCallback, useEffect, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'

const roleOptions = ['admin', 'apoteker', 'kasir', 'pelanggan']

const moduleOptions = [
  'auth',
  'category',
  'supplier',
  'medicine',
  'medicine_batch',
  'medicine_draft',
  'order',
]

const formatDateTime = (value) => {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

const labelize = (value) => value ? value.replaceAll('_', ' ') : '-'

export default function Audit() {
  const [logs, setLogs] = useState([])
  const [filters, setFilters] = useState({
    search: '',
    role: '',
    status: '',
    actor_email: '',
    http_status: '',
    module: '',
    date_from: '',
    date_to: '',
  })
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchLogs = useCallback(async (page = 1, nextFilters) => {
    setLoading(true)
    setError('')

    try {
      const params = {
        page,
        per_page: 10,
        search: nextFilters.search || undefined,
        role: nextFilters.role || undefined,
        status: nextFilters.status || undefined,
        actor_email: nextFilters.actor_email || undefined,
        http_status: nextFilters.http_status || undefined,
        module: nextFilters.module || undefined,
        date_from: nextFilters.date_from || undefined,
        date_to: nextFilters.date_to || undefined,
      }
      const response = await api.get('/admin/audit-logs', { params })
      setLogs(response.data.data || [])
      setPagination({
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
      })
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat audit log.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      fetchLogs(1, filters)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [fetchLogs, filters])

  const handleFilterChange = (event) => {
    const { name, value } = event.target
    setFilters((current) => ({ ...current, [name]: value }))
  }

  const resetFilters = () => {
    const emptyFilters = {
      search: '',
      role: '',
      status: '',
      actor_email: '',
      http_status: '',
      module: '',
      date_from: '',
      date_to: '',
    }
    setFilters(emptyFilters)
  }

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="audit" />
      <main className="ml-sidebar-width flex flex-1 flex-col">
        <AdminHeader
          title="Audit Log"
          subtitle="Lihat aktivitas pengguna dan riwayat perubahan sistem."
        />
        <div className="p-8">
          {error && (
            <div className="mb-5 rounded-lg border border-error-container bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">
              {error}
            </div>
          )}

          <section className="mb-6 rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <label className="text-sm font-semibold text-on-surface">
                Keyword
                <input
                  className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                  name="search"
                  value={filters.search}
                  onChange={handleFilterChange}
                  placeholder="Cari aksi, modul, deskripsi..."
                />
              </label>
              <label className="text-sm font-semibold text-on-surface">
                Role
                <select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="role" value={filters.role} onChange={handleFilterChange}>
                  <option value="">Semua</option>
                  {roleOptions.map((role) => <option key={role} value={role}>{role}</option>)}
                </select>
              </label>
              <label className="text-sm font-semibold text-on-surface">
                Status
                <select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="status" value={filters.status} onChange={handleFilterChange}>
                  <option value="">Semua</option>
                  <option value="success">Success</option>
                  <option value="failed">Failed</option>
                </select>
              </label>
              <label className="text-sm font-semibold text-on-surface">
                Modul
                <select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="module" value={filters.module} onChange={handleFilterChange}>
                  <option value="">Semua</option>
                  {moduleOptions.map((module) => <option key={module} value={module}>{labelize(module)}</option>)}
                </select>
              </label>
              <label className="text-sm font-semibold text-on-surface">
                Actor Email
                <input
                  className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary"
                  name="actor_email"
                  type="email"
                  value={filters.actor_email}
                  onChange={handleFilterChange}
                  placeholder="email@domain.com"
                />
              </label>
              <label className="text-sm font-semibold text-on-surface">
                HTTP Status
                <input
                  className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary"
                  name="http_status"
                  type="number"
                  min="100"
                  max="599"
                  value={filters.http_status}
                  onChange={handleFilterChange}
                  placeholder="422"
                />
              </label>
            </div>

            <div className="mt-4 flex flex-col gap-4 border-t border-outline-variant pt-4 md:flex-row md:items-end">
              <div className="grid flex-1 gap-4 sm:grid-cols-2 md:max-w-2xl">
                <label className="text-sm font-semibold text-on-surface">
                  Tanggal Mulai
                  <input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name="date_from" type="date" value={filters.date_from} onChange={handleFilterChange} />
                </label>
                <label className="text-sm font-semibold text-on-surface">
                  Tanggal Akhir
                  <input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name="date_to" type="date" value={filters.date_to} onChange={handleFilterChange} />
                </label>
              </div>
              <button className="rounded-lg border border-outline-variant bg-white px-5 py-2.5 font-bold text-on-surface" type="button" onClick={resetFilters}>Reset Filter</button>
            </div>
          </section>

          <div className="w-full overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
            <table className="w-full table-fixed text-left text-xs xl:text-sm">
              <thead className="bg-surface-container-low font-bold">
                <tr>
                  <th className="w-[9%] p-2 xl:p-3">Waktu</th>
                  <th className="w-[10%] p-2 xl:p-3">User</th>
                  <th className="w-[11%] p-2 xl:p-3">Actor Email</th>
                  <th className="w-[7%] p-2 xl:p-3">Role</th>
                  <th className="w-[7%] p-2 xl:p-3">Status</th>
                  <th className="w-[8%] p-2 xl:p-3">Modul</th>
                  <th className="w-[7%] p-2 xl:p-3">Aksi</th>
                  <th className="w-[5%] p-2 xl:p-3">HTTP</th>
                  <th className="w-[12%] p-2 xl:p-3">Failure Reason</th>
                  <th className="w-[16%] p-2 xl:p-3">Deskripsi</th>
                  <th className="w-[8%] p-2 xl:p-3">IP Address</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant">
                {loading ? (
                  <tr><td className="p-8 text-center text-on-surface-variant" colSpan="11">Memuat audit log...</td></tr>
                ) : logs.length === 0 ? (
                  <tr><td className="p-8 text-center text-on-surface-variant" colSpan="11">Audit log belum tersedia.</td></tr>
                ) : logs.map((log) => (
                  <tr key={log.id} className="align-top hover:bg-surface-container-low/50">
                    <td className="p-2 xl:p-3">{formatDateTime(log.created_at)}</td>
                    <td className="p-2 break-words xl:p-3">
                      <p className="font-bold text-on-surface">{log.user?.name || 'System'}</p>
                      {log.user?.email && <p className="break-all text-[10px] text-on-surface-variant xl:text-xs">{log.user.email}</p>}
                    </td>
                    <td className="break-all p-2 xl:p-3">{log.actor_email || '-'}</td>
                    <td className="p-1.5 text-center xl:p-2"><span className="inline-block whitespace-nowrap rounded-full bg-secondary-container px-1.5 py-1 text-[9px] font-bold text-secondary xl:px-2 xl:text-[10px]">{log.role || '-'}</span></td>
                    <td className="p-2 xl:p-3">
                      <span className={`inline-block whitespace-nowrap rounded-full px-1.5 py-1 text-[9px] font-bold xl:px-2 xl:text-[10px] ${log.status === 'failed' ? 'bg-error-container text-on-error-container' : 'bg-green-100 text-green-800'}`}>
                        {log.status || 'success'}
                      </span>
                    </td>
                    <td className="break-words p-2 capitalize xl:p-3">{labelize(log.module)}</td>
                    <td className="break-words p-2 font-bold text-primary capitalize xl:p-3">{labelize(log.action)}</td>
                    <td className="p-2 font-mono text-[10px] xl:p-3 xl:text-xs">{log.http_status || '-'}</td>
                    <td className="break-words p-2 text-error xl:p-3">{log.failure_reason || '-'}</td>
                    <td className="break-words p-2 text-on-surface-variant xl:p-3">{log.description || '-'}</td>
                    <td className="break-all p-2 font-mono text-[10px] xl:p-3 xl:text-xs">{log.ip_address || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-5 flex items-center justify-between text-sm text-on-surface-variant">
            <p>Total data: {pagination.total}</p>
            <div className="flex gap-2">
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page <= 1} onClick={() => fetchLogs(pagination.current_page - 1, filters)}>Sebelumnya</button>
              <span className="rounded-lg bg-surface-container-low px-4 py-2">{pagination.current_page} / {pagination.last_page}</span>
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page >= pagination.last_page} onClick={() => fetchLogs(pagination.current_page + 1, filters)}>Berikutnya</button>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
