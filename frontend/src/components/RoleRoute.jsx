import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const defaultRouteByRole = {
  admin: '/admin',
  apoteker: '/admin/prescription',
  kasir: '/admin/orders',
  pelanggan: '/catalog',
}

export default function RoleRoute({ roles = [] }) {
  const { user } = useAuth()
  const userRole = user?.role?.toLowerCase()
  const allowedRoles = roles.map((role) => role.toLowerCase())

  if (!allowedRoles.includes(userRole)) {
    return <Navigate to={defaultRouteByRole[userRole] || '/login'} replace />
  }

  return <Outlet />
}
