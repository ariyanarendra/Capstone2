import { useEffect, useState } from 'react'
import { ClipboardCheck, Eye, RefreshCw, Search, X } from 'lucide-react'
import { api } from '../../services/api'

const statusLabels = {
  diajukan: 'Diajukan',
  ditinjau: 'Ditinjau',
  selesai: 'Selesai',
  dibatalkan: 'Dibatalkan',
}

const formatDate = (value) => {
  const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value)
  if (Number.isNaN(date.getTime())) return 'Tanggal tidak valid'

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

export default function PerwalianManagementPage() {
  const [perwalians, setPerwalians] = useState([])
  const [advisors, setAdvisors] = useState([])
  const [summary, setSummary] = useState({ total: 0, diajukan: 0, ditinjau: 0, selesai: 0, dibatalkan: 0 })
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 })
  const [filters, setFilters] = useState({ search: '', dosen_id: '', status: '' })
  const [query, setQuery] = useState({ search: '', dosen_id: '', status: '', page: 1 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [selectedRecord, setSelectedRecord] = useState(null)

  useEffect(() => {
    let active = true

    api.perwalians(query)
      .then((response) => {
        if (!active) return
        setPerwalians(response.data || [])
        setAdvisors(response.advisors || [])
        setSummary(response.summary || { total: 0, diajukan: 0, ditinjau: 0, selesai: 0, dibatalkan: 0 })
        setMeta({
          current_page: response.current_page || 1,
          last_page: response.last_page || 1,
          from: response.from || 0,
          to: response.to || 0,
          total: response.total || 0,
        })
      })
      .catch((requestError) => active && setError(requestError.message))
      .finally(() => active && setLoading(false))

    return () => { active = false }
  }, [query])

  const applyFilters = (event) => {
    event.preventDefault()
    setError('')
    setLoading(true)
    setQuery({ ...filters, search: filters.search.trim(), page: 1 })
  }

  const resetFilters = () => {
    setFilters({ search: '', dosen_id: '', status: '' })
    setError('')
    setLoading(true)
    setQuery({ search: '', dosen_id: '', status: '', page: 1 })
  }

  return (
    <>
      <section className="page-banner page-banner--perwalian">
        <div className="page-banner__inner">
          <div>
            <span className="section-label section-label--light">MONITORING AKADEMIK</span>
            <h1>Data Perwalian</h1>
            <p>Pantau seluruh catatan konsultasi mahasiswa dan dosen wali.</p>
          </div>
          <ClipboardCheck aria-hidden="true" />
        </div>
      </section>

      <div className="portal-content user-content">
        <section className="perwalian-summary" aria-label="Ringkasan data perwalian">
          <div><span>Total catatan</span><strong>{summary.total}</strong><small>{summary.dibatalkan} dibatalkan</small></div>
          <div><span>Diajukan</span><strong>{summary.diajukan}</strong></div>
          <div><span>Ditinjau</span><strong>{summary.ditinjau}</strong></div>
          <div><span>Selesai</span><strong>{summary.selesai}</strong></div>
        </section>

        {error && <div className="page-notice page-notice--error">{error}</div>}

        <section className="user-panel">
          <header className="user-panel__heading">
            <div><span className="section-label">CATATAN KONSULTASI</span><h2>Riwayat perwalian mahasiswa</h2></div>
          </header>

          <form className="user-toolbar perwalian-toolbar" onSubmit={applyFilters}>
            <label className="search-field">
              <Search size={19} />
              <input value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Cari mahasiswa, NIM, atau topik" />
            </label>
            <select value={filters.dosen_id} onChange={(event) => setFilters({ ...filters, dosen_id: event.target.value })}>
              <option value="">Semua dosen wali</option>
              {advisors.map((advisor) => <option key={advisor.id} value={advisor.id}>{advisor.name}</option>)}
            </select>
            <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
              <option value="">Semua status</option>
              <option value="diajukan">Diajukan</option>
              <option value="ditinjau">Ditinjau</option>
              <option value="selesai">Selesai</option>
              <option value="dibatalkan">Dibatalkan</option>
            </select>
            <button className="filter-button" type="submit">Terapkan</button>
            <button className="reset-button" type="button" onClick={resetFilters}><RefreshCw size={16} /> Reset</button>
          </form>

          <div className="user-table-wrap">
            <table className="user-table perwalian-table">
              <thead><tr><th>Tanggal</th><th>Mahasiswa</th><th>Dosen wali</th><th>Topik</th><th>Status</th><th>Detail</th></tr></thead>
              <tbody>
                {loading && <tr><td colSpan="6" className="table-message">Memuat data perwalian...</td></tr>}
                {!loading && perwalians.length === 0 && <tr><td colSpan="6" className="table-message">Belum ada catatan yang sesuai dengan pencarian.</td></tr>}
                {!loading && perwalians.map((item) => (
                  <tr key={item.id}>
                    <td><strong className="record-date">{formatDate(item.tanggal)}</strong></td>
                    <td><div className="user-name"><span>{item.mahasiswa.name.charAt(0)}</span><div><strong>{item.mahasiswa.name}</strong><small>{item.mahasiswa.identifier}</small></div></div></td>
                    <td><div className="advisor-name"><strong>{item.dosen.name}</strong><small>{item.dosen.identifier}</small></div></td>
                    <td><span className="record-topic">{item.topik}</span></td>
                    <td><span className={`status-badge status-badge--${item.status}`}>{statusLabels[item.status]}</span></td>
                    <td><button className="assignment-button" onClick={() => setSelectedRecord(item)}><Eye size={15} /> Lihat</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <footer className="table-footer">
            <span>{meta.total ? `Menampilkan ${meta.from}–${meta.to} dari ${meta.total} catatan` : 'Tidak ada data'}</span>
            <div>
              <button disabled={meta.current_page <= 1 || loading} onClick={() => { setError(''); setLoading(true); setQuery((current) => ({ ...current, page: current.page - 1 })) }}>Sebelumnya</button>
              <strong>Halaman {meta.current_page} / {meta.last_page}</strong>
              <button disabled={meta.current_page >= meta.last_page || loading} onClick={() => { setError(''); setLoading(true); setQuery((current) => ({ ...current, page: current.page + 1 })) }}>Berikutnya</button>
            </div>
          </footer>
        </section>
      </div>

      {selectedRecord && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setSelectedRecord(null)}>
          <section className="user-modal record-modal" role="dialog" aria-modal="true" aria-labelledby="record-title">
            <header><div><span className="section-label">DETAIL KONSULTASI</span><h2 id="record-title">Catatan perwalian</h2></div><button onClick={() => setSelectedRecord(null)} aria-label="Tutup detail"><X /></button></header>
            <div className="record-detail">
              <div className="record-detail__identity">
                <div><span>Mahasiswa</span><strong>{selectedRecord.mahasiswa.name}</strong><small>{selectedRecord.mahasiswa.identifier} · {selectedRecord.mahasiswa.program_studi}</small></div>
                <div><span>Dosen wali</span><strong>{selectedRecord.dosen.name}</strong><small>{selectedRecord.dosen.identifier}</small></div>
              </div>
              <dl>
                <div><dt>Tanggal</dt><dd>{formatDate(selectedRecord.tanggal)}</dd></div>
                <div><dt>Status</dt><dd><span className={`status-badge status-badge--${selectedRecord.status}`}>{statusLabels[selectedRecord.status]}</span></dd></div>
                <div className="record-detail__wide"><dt>Topik konsultasi</dt><dd>{selectedRecord.topik}</dd></div>
                <div className="record-detail__wide"><dt>Hasil konsultasi</dt><dd>{selectedRecord.hasil_konsultasi}</dd></div>
                <div className="record-detail__wide"><dt>Rencana tindak lanjut</dt><dd>{selectedRecord.rencana_tindak_lanjut || 'Tidak ada catatan tindak lanjut.'}</dd></div>
                {selectedRecord.status === 'dibatalkan' && <div className="record-detail__wide"><dt>Alasan pembatalan</dt><dd>{selectedRecord.cancellation_reason || 'Mahasiswa tidak mencantumkan alasan.'}</dd></div>}
              </dl>
              <footer><button className="modal-save" onClick={() => setSelectedRecord(null)}>Tutup</button></footer>
            </div>
          </section>
        </div>
      )}
    </>
  )
}
