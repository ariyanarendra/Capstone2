import { useEffect, useState } from 'react'
import { CheckCircle2, ChevronRight, ClipboardCheck, GraduationCap, UserRound, UsersRound } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { api } from '../../../services/api'
import './AdminDashboardPage.css'

const initialSummary = { total: 0, diajukan: 0, ditinjau: 0, selesai: 0 }

const statusLabels = {
  diajukan: 'Diajukan',
  ditinjau: 'Ditinjau',
  selesai: 'Selesai',
}

const formatDate = (value) => {
  if (!value) return '—'
  const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value)
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date)
}

export default function AdminDashboardPage() {
  const navigate = useNavigate()
  const [studentCount, setStudentCount] = useState(0)
  const [advisorCount, setAdvisorCount] = useState(0)
  const [summary, setSummary] = useState(initialSummary)
  const [recentRecords, setRecentRecords] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true

    api.adminDashboard()
      .then((response) => {
        if (!active) return
        setStudentCount(response.summary?.active_students || 0)
        setAdvisorCount(response.summary?.active_advisors || 0)
        setSummary(response.summary || initialSummary)
        setRecentRecords(response.recent_perwalians || [])
      })
      .catch((requestError) => active && setError(requestError.message))
      .finally(() => active && setLoading(false))

    return () => { active = false }
  }, [])

  const completionRate = summary.total > 0 ? Math.round((summary.selesai / summary.total) * 100) : 0

  return (
    <>
      <section className="page-banner page-banner--admin-dashboard">
        <div className="page-banner__inner">
          <div>
            <span className="section-label section-label--light">SEMESTER GANJIL 2026/2027</span>
            <h1>Pusat Kendali Akademik</h1>
            <p>Pantau aktivitas perwalian terbaru dan kondisi layanan akademik.</p>
          </div>
          <GraduationCap aria-hidden="true" />
        </div>
      </section>

      <div className="portal-content admin-dashboard-content">
        {error && <div className="page-notice page-notice--error">{error}</div>}

        <section className="stat-grid" aria-label="Ringkasan akademik">
          <article className="stat-card">
            <UsersRound size={27} />
            <div><strong>{loading ? '—' : studentCount}</strong><span>Mahasiswa aktif</span><small>Akun mahasiswa yang dapat digunakan</small></div>
          </article>
          <article className="stat-card">
            <UserRound size={27} />
            <div><strong>{loading ? '—' : advisorCount}</strong><span>Dosen aktif</span><small>Dosen wali yang dapat ditugaskan</small></div>
          </article>
          <article className="stat-card stat-card--attention">
            <CheckCircle2 size={27} />
            <div><strong>{loading ? '—' : `${completionRate}%`}</strong><span>Perwalian selesai</span><small>{summary.selesai} dari {summary.total} catatan</small></div>
          </article>
        </section>

        <section className="user-panel admin-recent-panel">
          <header className="user-panel__heading admin-recent-panel__heading">
            <div><span className="section-label">AKTIVITAS TERBARU</span><h2>Riwayat perwalian terbaru</h2></div>
            <button onClick={() => navigate('/admin/data-perwalian')}>Lihat seluruh riwayat <ChevronRight size={17} /></button>
          </header>

          <div className="user-table-wrap">
            <table className="user-table admin-recent-table">
              <thead><tr><th>Tanggal</th><th>Mahasiswa</th><th>Dosen wali</th><th>Topik</th><th>Status</th><th>Tindakan</th></tr></thead>
              <tbody>
                {loading && <tr><td colSpan="6" className="table-message">Memuat riwayat perwalian...</td></tr>}
                {!loading && recentRecords.length === 0 && <tr><td colSpan="6" className="table-message">Belum ada catatan perwalian.</td></tr>}
                {!loading && recentRecords.map((record) => (
                  <tr key={record.id}>
                    <td><span className="admin-dashboard-date">{formatDate(record.tanggal)}</span></td>
                    <td><div className="user-name"><span>{record.mahasiswa?.name?.charAt(0)}</span><div><strong>{record.mahasiswa?.name}</strong><small>{record.mahasiswa?.identifier}</small></div></div></td>
                    <td><div className="admin-dashboard-advisor"><strong>{record.dosen?.name}</strong><small>{record.dosen?.identifier}</small></div></td>
                    <td><strong className="admin-dashboard-topic">{record.topik}</strong></td>
                    <td><span className={`status-badge status-badge--${record.status}`}>{statusLabels[record.status]}</span></td>
                    <td><button className="admin-dashboard-open" onClick={() => navigate('/admin/data-perwalian')}><ClipboardCheck size={15} /> Buka data</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </>
  )
}
