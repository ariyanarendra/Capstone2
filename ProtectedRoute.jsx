import { Navigate } from 'react-router-dom'
import PortalLayout from '../components/layout/PortalLayout'

export default function ProtectedRoute({ session, requiredRole, onLogout }) {
  if (!session) return <Navigate to="/login" replace />
  if (session.user.must_change_password) return <Navigate to="/change-password" replace />
  if (session.user.role !== requiredRole) return <Navigate to={`/${session.user.role}/dashboard`} replace />

  return <PortalLayout session={session} onLogout={onLogout} />
}
