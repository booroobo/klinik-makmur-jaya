import { useEffect, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'

const severityStyles = {
  info: 'border-blue-300 text-blue-700 bg-blue-50',
  success: 'border-secondary text-secondary bg-secondary-container/40',
  warning: 'border-amber-300 text-amber-700 bg-amber-50',
  critical: 'border-error text-error bg-error-container/40',
}

const severityLabels = {
  info: 'Info',
  success: 'Sukses',
  warning: 'Peringatan',
  critical: 'Kritis',
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID')
}

export default function Notifications() {
  const [notifications, setNotifications] = useState([])
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchNotifications = async (page = 1) => {
    setLoading(true)
    setError('')

    try {
      const response = await api.get('/notifications', { params: { page, per_page: 12 } })
      setNotifications(response.data.data || [])
      setPagination({
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
      })
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat notifikasi.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    // Halaman pusat notifikasi memuat daftar pertama kali dibuka.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchNotifications()
  }, [])

  const markRead = async (notification) => {
    await api.patch('/notifications/' + notification.id + '/read')
    fetchNotifications(pagination.current_page)
  }

  const markAllRead = async () => {
    await api.patch('/notifications/read-all')
    fetchNotifications(pagination.current_page)
  }

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="notifications" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader title="Pusat Notifikasi" subtitle="Alert in-app untuk order, resep, status pesanan, stok, dan kedaluwarsa." />
        <div className="p-6 xl:p-8">
          <div className="mb-5 flex flex-col gap-3 rounded-xl border border-outline-variant bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
            <div>
              <h2 className="font-bold">Daftar Notifikasi</h2>
              <p className="text-sm text-on-surface-variant">{pagination.total} notifikasi terlihat untuk user/role ini.</p>
            </div>
            <button className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white disabled:opacity-40" type="button" disabled={loading || notifications.length === 0} onClick={markAllRead}>
              Tandai Semua Dibaca
            </button>
          </div>

          {error && <div className="mb-5 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

          {loading ? (
            <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">Memuat notifikasi...</div>
          ) : notifications.length === 0 ? (
            <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">Belum ada notifikasi.</div>
          ) : (
            <section className="grid gap-4 xl:grid-cols-2">
              {notifications.map((notification) => {
                const style = severityStyles[notification.severity] || severityStyles.info

                return (
                  <article key={notification.id} className={'rounded-xl border-l-4 bg-white p-5 shadow-sm ' + style}>
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold uppercase">{severityLabels[notification.severity] || notification.severity}</span>
                          <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold uppercase">{notification.type}</span>
                          {!notification.read_at && <span className="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold uppercase text-white">Unread</span>}
                        </div>
                        <h4 className="mt-3 font-bold text-on-surface">{notification.title}</h4>
                        <p className="mt-1 text-sm text-on-surface-variant">{notification.message}</p>
                        <p className="mt-3 text-xs text-on-surface-variant">{formatDateTime(notification.created_at)}</p>
                      </div>
                      <button className="shrink-0 rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs font-bold text-primary disabled:opacity-40" type="button" disabled={Boolean(notification.read_at)} onClick={() => markRead(notification)}>
                        Mark Read
                      </button>
                    </div>
                  </article>
                )
              })}
            </section>
          )}

          {pagination.last_page > 1 && (
            <div className="mt-5 flex items-center justify-end gap-2 text-sm">
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page <= 1} onClick={() => fetchNotifications(pagination.current_page - 1)}>Sebelumnya</button>
              <span className="rounded-lg bg-surface-container-low px-4 py-2">{pagination.current_page} / {pagination.last_page}</span>
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page >= pagination.last_page} onClick={() => fetchNotifications(pagination.current_page + 1)}>Berikutnya</button>
            </div>
          )}
        </div>
      </main>
    </div>
  )
}
