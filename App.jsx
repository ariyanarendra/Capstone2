import { useState } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import AdvisorManagementPage from './pages/admin/AdvisorManagementPage'
import PerwalianManagementPage from './pages/admin/PerwalianManagementPage'
import UserManagementPage from './pages/admin/users/UserManagementPage'
import ReportPage from './pages/admin/reports/ReportPage'
import AdminDashboardPage from './pages/admin/dashboard/AdminDashboardPage'
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage'
import LoginPage from './pages/auth/LoginPage'
import TemporaryPasswordPage from './pages/auth/TemporaryPasswordPage'
import DosenDashboardPage from './pages/dosen/dashboard/DosenDashboardPage'
import HistoryPage from './pages/dosen/history/HistoryPage'
import StudentListPage from './pages/dosen/students/StudentListPage'
import MahasiswaDashboardPage from './pages/mahasiswa/dashboard/MahasiswaDashboardPage'
import MahasiswaHistoryPage from './pages/mahasiswa/history/MahasiswaHistoryPage'
import PerwalianFormPage from './pages/mahasiswa/perwalian/PerwalianFormPage'
import ProfilePage from './pages/profile/ProfilePage'
import ProtectedRoute from './routes/ProtectedRoute'
import { api, clearSession, getSession, updateSessionUser } from './services/api'
import './App.css'

export default function App() {
  const [session, setSession] = useState(getSession())
  const dashboardPath = session ? `/${session.user.role}/dashboard` : '/login'
  const landingPath = session?.user.must_change_password ? '/change-password' : dashboardPath
  const updateUser = (user) => {
    const nextSession = updateSessionUser(user)
    if (nextSession) setSession(nextSession)
  }
  const exitTemporaryPassword = async () => {
    try { await api.logout() } finally {
      clearSession()
      setSession(null)
    }
  }

  return (
    <Routes>
      <Route path="/login" element={session ? <Navigate to={landingPath} replace /> : <LoginPage onLogin={setSession} />} />
      <Route path="/forgot-password" element={session ? <Navigate to={landingPath} replace /> : <ForgotPasswordPage />} />
      <Route path="/reset-password" element={<Navigate to="/forgot-password" replace />} />
      <Route
        path="/change-password"
        element={!session
          ? <Navigate to="/login" replace />
          : !session.user.must_change_password
            ? <Navigate to={dashboardPath} replace />
            : <TemporaryPasswordPage user={session.user} onPasswordChanged={updateUser} onExit={exitTemporaryPassword} />}
      />

      <Route path="/admin" element={<ProtectedRoute session={session} requiredRole="admin" onLogout={() => setSession(null)} />}>
        <Route path="dashboard" element={<AdminDashboardPage />} />
        <Route path="data-dosen" element={<UserManagementPage key="data-dosen" role="dosen" />} />
        <Route path="data-mahasiswa" element={<UserManagementPage key="data-mahasiswa" role="mahasiswa" />} />
        <Route path="data-pengguna" element={<Navigate to="../data-mahasiswa" replace />} />
        <Route path="dosen-wali" element={<AdvisorManagementPage />} />
        <Route path="data-perwalian" element={<PerwalianManagementPage />} />
        <Route path="rekap-laporan" element={<ReportPage />} />
        <Route path="profil" element={<ProfilePage user={session?.user} onUserUpdate={updateUser} />} />
        <Route index element={<Navigate to="dashboard" replace />} />
        <Route path="*" element={<Navigate to="dashboard" replace />} />
      </Route>

      <Route path="/dosen" element={<ProtectedRoute session={session} requiredRole="dosen" onLogout={() => setSession(null)} />}>
        <Route path="dashboard" element={<DosenDashboardPage user={session?.user} />} />
        <Route path="mahasiswa-wali" element={<StudentListPage />} />
        <Route path="mahasiswa-wali/:studentId/riwayat" element={<HistoryPage />} />
        <Route path="histori-perwalian" element={<HistoryPage />} />
        <Route path="profil" element={<ProfilePage user={session?.user} onUserUpdate={updateUser} />} />
        <Route index element={<Navigate to="dashboard" replace />} />
        <Route path="*" element={<Navigate to="dashboard" replace />} />
      </Route>

      <Route path="/mahasiswa" element={<ProtectedRoute session={session} requiredRole="mahasiswa" onLogout={() => setSession(null)} />}>
        <Route path="dashboard" element={<MahasiswaDashboardPage user={session?.user} />} />
        <Route path="catat-perwalian" element={<PerwalianFormPage />} />
        <Route path="histori-perwalian" element={<MahasiswaHistoryPage />} />
        <Route path="profil" element={<ProfilePage user={session?.user} onUserUpdate={updateUser} />} />
        <Route index element={<Navigate to="dashboard" replace />} />
        <Route path="*" element={<Navigate to="dashboard" replace />} />
      </Route>

      <Route path="*" element={<Navigate to={landingPath} replace />} />
    </Routes>
  )
}
