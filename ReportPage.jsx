import { useEffect, useState } from 'react'
import { BookOpenCheck, Download, Printer, RefreshCw } from 'lucide-react'
import { api } from '../../../services/api'
import './ReportPage.css'

const emptySummary = { total: 0, diajukan: 0, ditinjau: 0, selesai: 0, completion_rate: 0 }

const csvCell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`

export default function ReportPage() {
  const [report, setReport] = useState({ summary: emptySummary, advisors: [], programs: [], generated_at: null })
  const [filters, setFilters] = useState({ program_studi: '', from: '', to: '' })
  const [query, setQuery] = useState({ program_studi: '', from: '', to: '' })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true

    api.perwalianReport(query)
      .then((response) => active && setReport(response))
      .catch((requestError) => active && setError(requestError.message))
      .finally(() => active && setLoading(false))

    return () => { active = false }
  }, [query])

  const applyFilters = (event) => {
    event.preventDefault()
    setError('')
    setLoading(true)
    setQuery(filters)
  }

  const resetFilters = () => {
    const emptyFilters = { program_studi: '', from: '', to: '' }
    setFilters(emptyFilters)
    setError('')
    setLoading(true)
    setQuery(emptyFilters)
  }

  const exportCsv = () => {
    const headings = ['NIP', 'Dosen wali', 'Program studi', 'Mahasiswa wali', 'Total catatan', 'Diajukan', 'Ditinjau', 'Selesai', 'Penyelesaian']
    const rows = report.advisors.map((advisor) => [
      advisor.identifier,
      advisor.name,
      advisor.program_studi,
      advisor.students,
      advisor.total,
      advisor.diajukan,
      advisor.ditinjau,
      advisor.selesai,
      `${advisor.completion_rate}%`,
    ])
    const csv = [headings, ...rows].map((row) => row.map(csvCell).join(',')).join('\n')
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }))
    const link = document.createElement('a')
    link.href = url
    link.download = 'rekap-perwalian-gatekampus.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <>
      <section className="page-banner page-banner--report">
        <div className="page-banner__inner">
          <div>
            <span className="section-label section-label--light">PELAPORAN AKADEMIK</span>
            <h1>Rekap &amp; Laporan</h1>
            <p>Ringkasan aktivitas perwalian berdasarkan dosen dan program studi.</p>
          </div>
          <BookOpenCheck aria-hidden="true" />
        </div>
      </section>

      <div className="portal-content report-content">
        <section className="report-actions">
          <form onSubmit={applyFilters}>
            <label><span>Program studi</span><select value={filters.program_studi} onChange={(event) => setFilters({ ...filters, program_studi: event.target.value })}><option value="">Semua program studi</option><option value="Informatika">Informatika</option><option value="Sistem Informasi">Sistem Informasi</option></select></label>
            <label><span>Dari tanggal</span><input type="date" value={filters.from} onChange={(event) => setFilters({ ...filters, from: event.target.value })} /></label>
            <label><span>Sampai tanggal</span><input type="date" value={filters.to} min={filters.from} onChange={(event) => setFilters({ ...filters, to: event.target.value })} /></label>
            <button className="filter-button">Tampilkan</button>
            <button className="reset-button" type="button" onClick={resetFilters}><RefreshCw size={16} /> Reset</button>
          </form>
          <div>
            <button onClick={() => window.print()}><Printer size={17} /> Cetak</button>
            <button className="report-export" onClick={exportCsv} disabled={loading || report.advisors.length === 0}><Download size={17} /> Unduh CSV</button>
          </div>
        </section>

        {error && <div className="page-notice page-notice--error">{error}</div>}

        <section className="report-summary" aria-label="Ringkasan laporan perwalian">
          <article><span>Total catatan</span><strong>{report.summary.total}</strong><small>Seluruh aktivitas perwalian</small></article>
          <article><span>Selesai</span><strong>{report.summary.selesai}</strong><small>Catatan telah diselesaikan</small></article>
          <article><span>Dalam proses</span><strong>{report.summary.diajukan + report.summary.ditinjau}</strong><small>Diajukan atau sedang ditinjau</small></article>
          <article><span>Penyelesaian</span><strong>{report.summary.completion_rate}%</strong><small>Persentase catatan selesai</small></article>
        </section>

        <section className="program-report">
          <header><span className="section-label">PROGRAM STUDI</span><h2>Capaian penyelesaian</h2></header>
          <div>
            {loading && <p className="report-loading">Memuat ringkasan laporan...</p>}
            {!loading && report.programs.length === 0 && <p className="report-loading">Belum ada data pada periode yang dipilih.</p>}
            {!loading && report.programs.map((program) => (
              <article key={program.name}>
                <div><strong>{program.name}</strong><span>{program.selesai} dari {program.total} catatan selesai</span></div>
                <b>{program.completion_rate}%</b>
                <div className="report-progress"><span style={{ width: `${program.completion_rate}%` }} /></div>
              </article>
            ))}
          </div>
        </section>

        <section className="user-panel report-table-panel">
          <header className="user-panel__heading"><div><span className="section-label">REKAP DOSEN</span><h2>Aktivitas dosen wali</h2></div></header>
          <div className="user-table-wrap">
            <table className="user-table report-table">
              <thead><tr><th>Dosen wali</th><th>Program studi</th><th>Mahasiswa</th><th>Total</th><th>Diajukan</th><th>Ditinjau</th><th>Selesai</th><th>Penyelesaian</th></tr></thead>
              <tbody>
                {loading && <tr><td colSpan="8" className="table-message">Memuat rekap dosen wali...</td></tr>}
                {!loading && report.advisors.map((advisor) => (
                  <tr key={advisor.id}>
                    <td><div className="user-name"><span>{advisor.name.charAt(0)}</span><div><strong>{advisor.name}</strong><small>{advisor.identifier}</small></div></div></td>
                    <td>{advisor.program_studi || '—'}</td>
                    <td>{advisor.students}</td>
                    <td><strong>{advisor.total}</strong></td>
                    <td>{advisor.diajukan}</td>
                    <td>{advisor.ditinjau}</td>
                    <td>{advisor.selesai}</td>
                    <td><span className="completion-value">{advisor.completion_rate}%</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <footer className="report-generated">Laporan dibuat: {report.generated_at ? new Date(report.generated_at).toLocaleString('id-ID') : '—'}</footer>
        </section>
      </div>
    </>
  )
}
