import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/axios'
import { useAuth } from '../context/AuthContext'

const fallbackNameByRole = {
  admin: 'Admin Demo',
  apoteker: 'Apoteker Demo',
  kasir: 'Kasir Demo',
}

const severityStyles = {
  info: { icon: 'info', badge: 'Info', badgeClass: 'bg-blue-100 text-blue-700', iconClass: 'text-blue-600' },
  success: { icon: 'task_alt', badge: 'Sukses', badgeClass: 'bg-secondary-container text-secondary', iconClass: 'text-secondary' },
  warning: { icon: 'warning', badge: 'Peringatan', badgeClass: 'bg-amber-100 text-amber-700', iconClass: 'text-amber-600' },
  critical: { icon: 'priority_high', badge: 'Kritis', badgeClass: 'bg-error-container text-on-error-container', iconClass: 'text-error' },
}

export default function AdminHeader({ title, subtitle }) {
  const { user } = useAuth()
  const [open, setOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const role = user?.role?.toLowerCase()
  const displayName = user?.name || fallbackNameByRole[role] || 'Pengguna'

  const fetchUnreadCount = useCallback(async () => {
    if (!user) return

    try {
      const response = await api.get('/notifications/unread-count')
      setUnreadCount(response.data.data?.count || 0)
    } catch {
      setUnreadCount(0)
    }
  }, [user])

  const fetchNotifications = useCallback(async () => {
    if (!user) return

    try {
      const response = await api.get('/notifications', { params: { per_page: 5 } })
      setNotifications(response.data.data || [])
    } catch {
      setNotifications([])
    }
  }, [user])

  useEffect(() => {
    // Count notifikasi disinkronkan dari API dan dipolling untuk simulasi real-time.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchUnreadCount()
    const intervalId = window.setInterval(fetchUnreadCount, 45000)

    return () => window.clearInterval(intervalId)
  }, [fetchUnreadCount])

  useEffect(() => {
    if (open) {
      // Isi dropdown dimuat saat pengguna membuka panel notifikasi.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      fetchNotifications()
    }
  }, [fetchNotifications, open])

  const handleMarkAllRead = async () => {
    await api.patch('/notifications/read-all')
    setUnreadCount(0)
    fetchNotifications()
  }

  return (
    <header className="sticky top-0 z-40 flex h-20 items-center justify-between border-b border-outline-variant bg-white px-8 shadow-sm">
      <div>
        <h1 className="text-2xl font-bold text-on-surface">{title}</h1>
        <p className="mt-1 text-sm text-on-surface-variant">{subtitle}</p>
      </div>
      <div className="flex items-center gap-5 border-l border-outline-variant pl-6">
        <div className="relative">
          <button
            className="relative rounded-full p-2 transition-all hover:bg-surface-container-low"
            type="button"
            onClick={() => setOpen((current) => !current)}
          >
            <span className="material-symbols-outlined text-primary">notifications</span>
            {unreadCount > 0 && (
              <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white">
                {unreadCount}
              </span>
            )}
          </button>
          {open && (
            <div className="absolute right-0 top-full mt-3 w-[380px] overflow-hidden rounded-xl border border-outline-variant bg-white shadow-xl">
              <div className="flex items-center justify-between border-b border-outline-variant bg-surface-container-low px-4 py-3">
                <div>
                  <p className="font-bold text-on-surface">Notifikasi</p>
                  <p className="text-xs text-on-surface-variant capitalize">{role || 'pengguna'} - polling 45 detik</p>
                </div>
                <button className="rounded-lg px-3 py-1.5 text-xs font-bold text-primary hover:bg-white" type="button" onClick={handleMarkAllRead}>
                  Tandai semua
                </button>
              </div>
              <div className="max-h-[360px] overflow-y-auto p-3">
                {notifications.length === 0 ? (
                  <p className="py-8 text-center text-sm text-on-surface-variant">Belum ada notifikasi.</p>
                ) : (
                  <div className="space-y-2">
                    {notifications.map((notification) => <NotificationItem key={notification.id} notification={notification} />)}
                  </div>
                )}
              </div>
              <Link className="block border-t border-outline-variant px-4 py-3 text-center text-sm font-bold text-primary hover:bg-surface-container-low" to="/admin/notifications" onClick={() => setOpen(false)}>
                Lihat semua notifikasi
              </Link>
            </div>
          )}
        </div>
        <div className="text-right">
          <p className="text-sm font-bold text-on-surface">{displayName}</p>
          <p className="text-[10px] capitalize text-on-surface-variant">{role || 'user'}</p>
        </div>
        <div className="flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary bg-surface-container-high text-primary">
          <span className="material-symbols-outlined">account_circle</span>
        </div>
      </div>
    </header>
  )
}

function NotificationItem({ notification }) {
  const style = severityStyles[notification.severity] || severityStyles.info

  return (
    <article className={'flex gap-3 rounded-lg border p-3 ' + (notification.read_at ? 'border-outline-variant bg-white' : 'border-primary/20 bg-primary-container/10')}>
      <span className={'material-symbols-outlined mt-0.5 ' + style.iconClass}>{style.icon}</span>
      <div className="min-w-0 flex-1">
        <div className="mb-1 flex items-start justify-between gap-3">
          <h3 className="text-sm font-bold text-on-surface">{notification.title}</h3>
          <span className={'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold ' + style.badgeClass}>{style.badge}</span>
        </div>
        <p className="text-xs text-on-surface-variant">{notification.message}</p>
      </div>
    </article>
  )
}
