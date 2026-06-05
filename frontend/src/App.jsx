import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import ProtectedRoute from './components/ProtectedRoute'
import RoleRoute from './components/RoleRoute'
import { useAuth } from './context/AuthContext'
import Login from './pages/auth/Login'
import Register from './pages/auth/Register'
import AdminDashboard from './pages/admin/AdminDashboard'
import Audit from './pages/admin/Audit'
import Customers from './pages/admin/Customers'
import Inventory from './pages/admin/Inventory'
import MedicineImport from './pages/admin/MedicineImport'
import Orders from './pages/admin/Orders'
import Notifications from './pages/admin/Notifications'
import PrescriptionVerification from './pages/admin/PrescriptionVerification'
import Reports from './pages/admin/Reports'
import Suppliers from './pages/admin/Suppliers'
import AboutUs from './pages/customer/AboutUs'
import Cart from './pages/customer/Cart'
import Catalog from './pages/customer/Catalog'
import Checkout from './pages/customer/Checkout'
import ContactUs from './pages/customer/ContactUs'
import MedicineDetail from './pages/customer/MedicineDetail'
import MyOrders from './pages/customer/MyOrders'
import OrderDetail from './pages/customer/OrderDetail'

const roleDashboardPath = {
  admin: '/admin',
  apoteker: '/admin/prescription',
  kasir: '/admin/orders',
  pelanggan: '/catalog',
}

function DashboardRedirect() {
  const { user } = useAuth()
  const path = roleDashboardPath[user?.role?.toLowerCase()] || '/login'

  return <Navigate to={path} replace />
}

function ForbiddenPage() {
  return (
    <main className="grid min-h-screen place-items-center bg-slate-50 px-4">
      <section className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm">
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">
          Akses ditolak
        </p>
        <h1 className="mt-3 text-2xl font-bold text-slate-950">
          Role Anda tidak memiliki akses ke halaman ini.
        </h1>
      </section>
    </main>
  )
}

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Navigate to="/catalog" replace />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/catalog" element={<Catalog />} />
        <Route path="/catalog/:id" element={<MedicineDetail />} />
        <Route path="/about-us" element={<AboutUs />} />
        <Route path="/contact-us" element={<ContactUs />} />
        <Route element={<ProtectedRoute />}>
          <Route path="/dashboard" element={<DashboardRedirect />} />
          <Route element={<RoleRoute roles={['pelanggan']} />}>
            <Route path="/cart" element={<Cart />} />
            <Route path="/checkout" element={<Checkout />} />
            <Route path="/my-orders" element={<MyOrders />} />
            <Route path="/my-orders/:id" element={<OrderDetail />} />
          </Route>
          <Route element={<RoleRoute roles={['admin']} />}>
            <Route path="/admin" element={<AdminDashboard />} />
            <Route path="/admin/customers" element={<Customers />} />
          </Route>
          <Route element={<RoleRoute roles={['admin', 'apoteker']} />}>
            <Route path="/admin/inventory" element={<Inventory />} />
            <Route path="/admin/suppliers" element={<Suppliers />} />
          </Route>
          <Route element={<RoleRoute roles={['admin']} />}>
            <Route path="/admin/medicines/import" element={<MedicineImport />} />
          </Route>
          <Route element={<RoleRoute roles={['admin', 'apoteker']} />}>
            <Route path="/admin/prescription" element={<PrescriptionVerification />} />
          </Route>
          <Route element={<RoleRoute roles={['admin', 'kasir']} />}>
            <Route path="/admin/orders" element={<Orders />} />
          </Route>
          <Route element={<RoleRoute roles={['admin']} />}>
            <Route path="/admin/reports" element={<Reports />} />
            <Route path="/admin/audit" element={<Audit />} />
          </Route>
          <Route element={<RoleRoute roles={['admin', 'apoteker', 'kasir']} />}>
            <Route path="/admin/notifications" element={<Notifications />} />
          </Route>
        </Route>
        <Route path="/403" element={<ForbiddenPage />} />
        <Route path="*" element={<Navigate to="/catalog" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
