import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../api/axios'
import { useAuth } from '../context/AuthContext'
import {
  isNotificationUnread,
  markAllNotificationsReadLocally,
  markNotificationReadLocally,
  sortNotificationsByPriority,
  unreadIndicatorClass,
} from '../utils/notifications'

const avatarUrl =
  'https://lh3.googleusercontent.com/aida-public/AB6AXuD6dXGlvpYW5LsuQ5BcsOfN7xDAHOi1oH7DRZTonhblJUhYIp96vs_Y9R_VfOEUNwfP-15s6Cb5E0C-w8lOChoOBVu3R8FLhaYeoms-jG3hNPT36ZlWVRGoDwYaIZfFZuGoLwAgqGRB81ZRq7_igSpFz4cWITgCBkGAVZAu4_X5xlYo1I2wGoajXiFMO3N7CkJB_HH2epfDiZNEaHh29wFgHnGivyzYFF9BpLZnwzKax4T5XiWYYkFB4Xs2sTTy_2neixxpfR4hON8'

const notificationStyles = {
  warning: {
    icon: 'warning',
    label: 'Warning',
    badgeClass: 'bg-amber-100 text-amber-700',
    iconClass: 'text-amber-600',
  },
  info: {
    icon: 'info',
    label: 'Info',
    badgeClass: 'bg-blue-100 text-blue-700',
    iconClass: 'text-blue-600',
  },
  critical: {
    icon: 'priority_high',
    label: 'Critical',
    badgeClass: 'bg-red-100 text-red-700',
    iconClass: 'text-red-600',
  },
  success: {
    icon: 'task_alt',
    label: 'Sukses',
    badgeClass: 'bg-emerald-100 text-emerald-700',
    iconClass: 'text-emerald-600',
  },
}

export default function Navbar({ role = 'customer' }) {
  const navigate = useNavigate()
  const { logout, user } = useAuth()
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [cartCount, setCartCount] = useState(0)
  const isCustomer = user?.role?.toLowerCase() === 'pelanggan'

  useEffect(() => {
    const fetchCartCount = async () => {
      if (!isCustomer) {
        setCartCount(0)
        return
      }

      try {
        const response = await api.get('/cart')
        setCartCount(response.data.data?.total_quantity || 0)
      } catch {
        setCartCount(0)
      }
    }

    fetchCartCount()
  }, [isCustomer])

  useEffect(() => {
    const fetchUnreadCount = async () => {
      if (!user) {
        setUnreadCount(0)
        return
      }

      try {
        const response = await api.get('/notifications/unread-count')
        setUnreadCount(response.data.data?.count || 0)
      } catch {
        setUnreadCount(0)
      }
    }

    fetchUnreadCount()
    const intervalId = window.setInterval(fetchUnreadCount, 45000)

    return () => window.clearInterval(intervalId)
  }, [user])

  useEffect(() => {
    const fetchNotifications = async () => {
      if (!user || !notificationsOpen) return

      try {
        const response = await api.get('/notifications', { params: { per_page: 5 } })
        setNotifications(sortNotificationsByPriority(response.data.data || []))
      } catch {
        setNotifications([])
      }
    }

    fetchNotifications()
  }, [notificationsOpen, user])

  const markAllNotificationsRead = async () => {
    await api.patch('/notifications/read-all')
    setUnreadCount(0)
    setNotifications((current) => markAllNotificationsReadLocally(current))
  }

  const openNotification = async (notification) => {
    const wasUnread = isNotificationUnread(notification)
    if (wasUnread) await api.patch('/notifications/' + notification.id + '/read')
    setNotifications((current) => markNotificationReadLocally(current, notification.id))
    setNotificationsOpen(false)
    setUnreadCount((current) => Math.max(0, current - (wasUnread ? 1 : 0)))
    if (notification.target_url) navigate(notification.target_url)
  }

  const handleLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <nav className="sticky top-0 z-50 border-b border-outline-variant bg-white">
      <div className="mx-auto flex h-16 w-full max-w-container-max items-center justify-between px-margin-mobile md:px-margin-desktop">
        <div className="flex items-center gap-8">
          <Link to="/catalog" className="flex items-center gap-2 text-xl font-bold text-primary">
            <span className="material-symbols-outlined text-[32px] text-primary">
              health_and_safety
            </span>
            Klinik Makmur Jaya
          </Link>
          {role === 'customer' && (
            <div className="hidden items-center gap-6 md:flex">
              <Link to="/catalog" className="font-semibold text-on-surface-variant hover:text-primary">
                Katalog
              </Link>
              <Link to="/about-us" className="text-on-surface-variant hover:text-primary">
                Tentang Kami
              </Link>
              <Link to="/contact-us" className="text-on-surface-variant hover:text-primary">
                Hubungi Kami
              </Link>
            </div>
          )}
        </div>
        <div className="flex items-center gap-4">
          <div className="relative">
            <button
              className="relative rounded-full p-2 hover:bg-surface-container-low"
              type="button"
              onClick={() => setNotificationsOpen((current) => !current)}
            >
              <span className="material-symbols-outlined text-primary">notifications</span>
              {unreadCount > 0 && (
                <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white">
                  {unreadCount}
                </span>
              )}
            </button>
            {notificationsOpen && (
              <div className="absolute right-0 top-full z-50 mt-3 w-[min(88vw,360px)] overflow-hidden rounded-xl border border-outline-variant bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-outline-variant bg-surface-container-low px-4 py-3">
                  <div>
                    <p className="font-bold text-on-surface">Notifikasi</p>
                    <p className="text-xs text-on-surface-variant">Update terbaru - polling 45 detik</p>
                  </div>
                  <button
                    className="rounded-lg px-3 py-1.5 text-xs font-bold text-primary hover:bg-white"
                    type="button"
                    onClick={markAllNotificationsRead}
                  >
                    Tandai semua
                  </button>
                </div>
                <div className="max-h-[320px] overflow-y-auto p-3">
                  {notifications.length === 0 ? (
                    <p className="py-8 text-center text-sm text-on-surface-variant">Belum ada notifikasi.</p>
                  ) : <div className="space-y-2">
                    {notifications.map((notification) => {
                      const style = notificationStyles[notification.severity] || notificationStyles.info

                      return (
                        <button
                          key={notification.id}
                          className="flex w-full gap-3 rounded-lg border border-outline-variant bg-white p-3 text-left hover:bg-surface-container-low"
                          type="button"
                          onClick={() => openNotification(notification)}
                        >
                          <span className={`material-symbols-outlined mt-0.5 ${style.iconClass}`}>
                            {style.icon}
                          </span>
                          <div className="min-w-0 flex-1">
                            <div className="mb-1 flex items-start justify-between gap-3">
                              <p className="text-sm font-bold text-on-surface">{notification.title}</p>
                              <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold ${style.badgeClass}`}>
                                {style.label}
                              </span>
                            </div>
                            <p className="text-xs text-on-surface-variant">{notification.message}</p>
                          </div>
                          <span className={`mt-1 h-3 w-3 shrink-0 rounded-full ring-4 ${unreadIndicatorClass(notification)}`} title={isNotificationUnread(notification) ? 'Belum dibaca' : 'Sudah dibaca'} />
                        </button>
                      )
                    })}
                  </div>}
                </div>
              </div>
            )}
          </div>
          <Link to="/cart" className="relative rounded-full p-2 hover:bg-surface-container-low">
            <span className="material-symbols-outlined text-primary">shopping_cart</span>
            {role === 'customer' && cartCount > 0 && (
              <span className="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-error text-[10px] text-white">
                {cartCount}
              </span>
            )}
          </Link>
          {user ? (
            <div className="group relative">
              <button
                className="flex items-center gap-2 rounded-full border border-outline-variant bg-white py-1 pl-1 pr-3 hover:bg-surface-container-low"
                type="button"
                title={user.name || user.role || 'Profil'}
              >
                <span className="h-8 w-8 overflow-hidden rounded-full border border-outline-variant">
                  <img alt="User" className="h-full w-full object-cover" src={avatarUrl} />
                </span>
                <span className="hidden max-w-32 truncate text-left text-sm font-semibold text-on-surface md:block">
                  {user.name || user.role}
                </span>
                <span className="material-symbols-outlined text-[18px] text-on-surface-variant">
                  expand_more
                </span>
              </button>
              <div className="invisible absolute right-0 top-full z-50 mt-2 w-48 rounded-lg border border-outline-variant bg-white p-2 opacity-0 shadow-lg transition-all group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                <div className="border-b border-outline-variant px-3 py-2">
                  <p className="truncate text-sm font-bold text-on-surface">{user.name || 'Pengguna'}</p>
                  <p className="text-xs capitalize text-on-surface-variant">{user.role || 'pelanggan'}</p>
                </div>
                {isCustomer && (
                  <Link
                    className="mt-2 flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-semibold text-on-surface hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none"
                    to="/my-orders"
                  >
                    <span className="material-symbols-outlined text-[18px] text-primary">receipt_long</span>
                    Pesanan Saya
                  </Link>
                )}
                <button
                  className="mt-2 flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-semibold text-error hover:bg-error-container"
                  type="button"
                  onClick={handleLogout}
                >
                  <span className="material-symbols-outlined text-[18px]">logout</span>
                  Keluar
                </button>
              </div>
            </div>
          ) : (
            <Link
              className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:opacity-90"
              to="/login"
            >
              Masuk
            </Link>
          )}
        </div>
      </div>
    </nav>
  )
}
