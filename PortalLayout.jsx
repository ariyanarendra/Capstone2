import { useState } from 'react'
import { ChevronDown, ChevronRight, HelpCircle, LogOut, Menu, UserRound, X } from 'lucide-react'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import Brand from '../Brand'
import ErrorBoundary from '../ErrorBoundary'
import { menus, roleLabels } from '../../config/portal'
import { api, clearSession } from '../../services/api'

const helpContent = {
  admin: ['Kelola akun melalui Data Pengguna.', 'Tetapkan pembimbing melalui Dosen Wali.', 'Pantau dan rekap seluruh Data Perwalian.'],
  dosen: ['Periksa daftar pada Mahasiswa Wali.', 'Tinjau catatan pada Histori Perwalian.', 'Pastikan status konsultasi diperbarui.'],
  mahasiswa: ['Catat hasil konsultasi melalui Catat Perwalian.', 'Periksa perkembangan melalui Histori Perwalian.', 'Hubungi admin jika dosen wali belum ditetapkan.'],
}

export default function PortalLayout({ session, onLogout }) {
  const location = useLocation()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [profileOpen, setProfileOpen] = useState(false)
  const [helpOpen, setHelpOpen] = useState(false)
  const activePath = location.pathname.split('/').filter(Boolean).pop() || 'dashboard'
  const activeLabel = menus[session.user.role].find((item) => item.path === activePath)?.label || activePath.replaceAll('-', ' ')

  const logout = async () => {
    try { await api.logout() } finally {
      clearSession()
      onLogout()
      navigate('/login', { replace: true })
    }
  }

  const openProfile = () => {
    setProfileOpen(false)
    setHelpOpen(false)
    navigate(`/${session.user.role}/profil`)
  }

  return (
    <div className="portal-shell">
      <header className="portal-header">
        <div className="utility-bar">
          <div className="header-container">
            <span>Portal Akademik STMIK Bandung</span>
            <div className="utility-actions">
              <button onClick={openProfile}><UserRound size={15} /> Profil</button>
              <button onClick={() => { setHelpOpen((value) => !value); setProfileOpen(false) }}><HelpCircle size={15} /> Bantuan</button>
              <button onClick={logout}><LogOut size={15} /> Keluar</button>
              {helpOpen && (
                <section className="help-menu" aria-label="Panduan penggunaan">
                  <div><span className="section-label">PANDUAN SINGKAT</span><button onClick={() => setHelpOpen(false)} aria-label="Tutup bantuan"><X size={17} /></button></div>
                  <strong>Bantuan {roleLabels[session.user.role]}</strong>
                  <ol>{helpContent[session.user.role].map((item) => <li key={item}>{item}</li>)}</ol>
                  <p>Kendala akun dapat disampaikan kepada Administrator Akademik.</p>
                </section>
              )}
            </div>
          </div>
        </div>

        <div className="masthead header-container">
          <Brand />
          <div className="profile-area">
            <button className="profile" onClick={() => { setProfileOpen((value) => !value); setHelpOpen(false) }} aria-expanded={profileOpen} aria-haspopup="true">
              <div className="avatar">{session.user.name.charAt(0)}</div>
              <div><strong>{session.user.name}</strong><span>{roleLabels[session.user.role]}</span></div>
              <ChevronDown className={profileOpen ? 'profile-chevron--open' : ''} size={17} />
            </button>
            {profileOpen && (
              <section className="profile-menu" aria-label="Informasi akun">
                <span className="section-label">AKUN AKTIF</span>
                <strong>{session.user.name}</strong>
                <p>{session.user.identifier} · {roleLabels[session.user.role]}</p>
                <small>{session.user.email}</small>
                <button onClick={openProfile}><UserRound size={16} /> Buka profil saya</button>
                <button className="profile-menu__logout" onClick={logout}><LogOut size={16} /> Keluar dari akun</button>
              </section>
            )}
          </div>
          <button className="menu-toggle" onClick={() => setOpen(true)} aria-label="Buka menu"><Menu /></button>
        </div>

        <nav className={`portal-nav ${open ? 'portal-nav--open' : ''}`}>
          <div className="mobile-nav-heading"><Brand light /><button onClick={() => setOpen(false)} aria-label="Tutup menu"><X /></button></div>
          <div className="header-container portal-nav__inner">
            {menus[session.user.role].map(({ label, icon: Icon, path }) => (
              <button
                key={label}
                className={activePath === path ? 'active' : ''}
                onClick={() => {
                  navigate(`/${session.user.role}/${path}`)
                  setOpen(false)
                }}
              >
                <Icon size={18} /><span>{label}</span>
              </button>
            ))}
          </div>
        </nav>
      </header>

      <main>
        <div className="breadcrumb-bar"><div className="header-container"><span>GateKampus</span><ChevronRight size={14} /><strong>{activeLabel}</strong></div></div>
        <ErrorBoundary key={location.pathname}><Outlet /></ErrorBoundary>
      </main>

      <footer className="portal-footer">
        <div className="header-container"><span>© 2026 GateKampus · STMIK Bandung</span><span>Portal Perwalian Mahasiswa · v1.0</span></div>
      </footer>

      {open && <div className="nav-overlay" onClick={() => setOpen(false)} />}
    </div>
  )
}
