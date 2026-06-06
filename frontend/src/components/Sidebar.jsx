import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const navItems = [
  { id: 'dashboard', label: 'Dashboard', icon: 'dashboard', path: '/admin' },
  { id: 'customers', label: 'Pelanggan', icon: 'groups', path: '/admin/customers' },
  { id: 'inventory', label: 'Manajemen Obat', icon: 'medication', path: '/admin/inventory' },
  { id: 'medicine_import', label: 'Import Obat', icon: 'upload_file', path: '/admin/medicines/import' },
  { id: 'suppliers', label: 'Supplier', icon: 'local_shipping', path: '/admin/suppliers' },
  { id: 'prescription', label: 'Verifikasi Resep', icon: 'description', path: '/admin/prescription' },
  { id: 'orders', label: 'Proses Pesanan', icon: 'shopping_bag', path: '/admin/orders' },
  { id: 'reports', label: 'Laporan', icon: 'assessment', path: '/admin/reports' },
  { id: 'audit', label: 'Audit Log', icon: 'history', path: '/admin/audit' },
]

const menuByRole = {
  admin: ['dashboard', 'customers', 'inventory', 'medicine_import', 'suppliers', 'prescription', 'orders', 'reports', 'audit'],
  apoteker: ['prescription', 'inventory', 'suppliers'],
  kasir: ['orders'],
}

const labelByRole = {
  admin: 'Admin Dashboard',
  apoteker: 'Dashboard Apoteker',
  kasir: 'Dashboard Kasir',
}

const primaryActionByRole = {
  admin: { label: 'Tambah Stok', icon: 'add', path: '/admin/inventory' },
  apoteker: { label: 'Verifikasi Resep', icon: 'description', path: '/admin/prescription' },
  kasir: { label: 'Proses Pesanan', icon: 'shopping_bag', path: '/admin/orders' },
}

export default function Sidebar({ active }) {
  const navigate = useNavigate()
  const { logout, user } = useAuth()
  const userRole = user?.role?.toLowerCase()
  const allowedMenuIds = menuByRole[userRole] || []
  const visibleNavItems = navItems.filter((item) => allowedMenuIds.includes(item.id))
  const roleLabel = labelByRole[userRole] || 'Dashboard'
  const primaryAction = primaryActionByRole[userRole]

  const handleLogout = async () => {
    await logout?.()
    navigate('/login', { replace: true })
  }

  return (
    <aside className="fixed left-0 top-0 z-50 flex h-screen w-sidebar-width flex-col gap-2 border-r border-outline-variant bg-surface-container-low p-4">
      <div className="mb-8 px-2">
        <h1 className="text-xl font-bold text-primary">Klinik MJ</h1>
        <p className="text-xs text-on-surface-variant">{roleLabel}</p>
      </div>
      <nav className="flex-grow space-y-1">
        {visibleNavItems.map((item) => (
          <Link
            key={item.id}
            to={item.path}
            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 transition-all ${
              active === item.id
                ? 'bg-secondary-container font-bold text-secondary'
                : 'text-on-surface-variant hover:bg-surface-container-high'
            }`}
          >
            <span className="material-symbols-outlined">{item.icon}</span>
            <span className="text-sm">{item.label}</span>
          </Link>
        ))}
      </nav>
      {primaryAction && (
        <button
          className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary py-3 font-semibold text-on-primary transition-transform active:scale-95"
          type="button"
          onClick={() => navigate(primaryAction.path)}
        >
          <span className="material-symbols-outlined">{primaryAction.icon}</span> {primaryAction.label}
        </button>
      )}
      <div className="mt-auto space-y-1 border-t border-outline-variant pt-4">
        <button
          className="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-error transition-all hover:bg-error-container"
          type="button"
          onClick={handleLogout}
        >
          <span className="material-symbols-outlined">logout</span> Keluar
        </button>
      </div>
    </aside>
  )
}
