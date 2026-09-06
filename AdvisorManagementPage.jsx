import { useEffect, useState } from 'react'
import { PencilLine, RefreshCw, Search, UserRound, X } from 'lucide-react'
import { api } from '../../services/api'

export default function AdvisorManagementPage() {
  const [assignments, setAssignments] = useState([])
  const [advisors, setAdvisors] = useState([])
  const [summary, setSummary] = useState({ students: 0, assigned: 0, unassigned: 0 })
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 })
  const [filters, setFilters] = useState({ search: '', dosen_id: '', status: '' })
  const [query, setQuery] = useState({ search: '', dosen_id: '', status: '', page: 1 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const [selectedStudent, setSelectedStudent] = useState(null)
  const [selectedAdvisor, setSelectedAdvisor] = useState('')
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')

  useEffect(() => {
    let active = true

    api.advisorAssignments(query)
      .then((response) => {
        if (!active) return
        setAssignments(response.data || [])
        setAdvisors(response.advisors || [])
        setSummary(response.summary || { students: 0, assigned: 0, unassigned: 0 })
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
  }, [query, reloadKey])

  const applyFilters = (event) => {
    event.preventDefault()
    setNotice('')
    setError('')
    setLoading(true)
    setQuery({ ...filters, search: filters.search.trim(), page: 1 })
  }

  const resetFilters = () => {
    setFilters({ search: '', dosen_id: '', status: '' })
    setNotice('')
    setError('')
    setLoading(true)
    setQuery({ search: '', dosen_id: '', status: '', page: 1 })
  }

  const openAssignment = (student) => {
    setSelectedStudent(student)
    const currentAdvisorIsEligible = advisors.some((advisor) => (
      advisor.id === student.dosen_id
      && advisor.program_studi === student.program_studi
    ))
    setSelectedAdvisor(currentAdvisorIsEligible ? String(student.dosen_id) : '')
    setFormError('')
  }

  const saveAssignment = async (event) => {
    event.preventDefault()
    setSaving(true)
    setFormError('')
    try {
      const response = await api.updateAdvisorAssignment(
        selectedStudent.student_id,
        selectedAdvisor ? Number(selectedAdvisor) : null,
      )
      setSelectedStudent(null)
      setNotice(response.message || 'Penugasan dosen wali berhasil diperbarui.')
      setError('')
      setLoading(true)
      setReloadKey((value) => value + 1)
    } catch (requestError) {
      setFormError(requestError.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <>
      <section className="page-banner page-banner--advisors">
        <div className="page-banner__inner">
          <div>
            <span className="section-label section-label--light">PENGELOLAAN PERWALIAN</span>
            <h1>Dosen Wali</h1>
            <p>Tetapkan dosen wali untuk setiap mahasiswa secara teratur dan mudah diperiksa.</p>
          </div>
          <UserRound aria-hidden="true" />
        </div>
      </section>

      <div className="portal-content user-content">
        <section className="assignment-summary" aria-label="Ringkasan penugasan dosen wali">
          <div><span>Total mahasiswa</span><strong>{summary.students}</strong></div>
          <div><span>Sudah ditetapkan</span><strong>{summary.assigned}</strong></div>
          <div><span>Belum ditetapkan</span><strong>{summary.unassigned}</strong></div>
          <p>Satu mahasiswa memiliki satu dosen wali aktif. Penugasan dapat diganti oleh administrator.</p>
        </section>

        {notice && <div className="page-notice page-notice--success">{notice}</div>}
        {error && <div className="page-notice page-notice--error">{error}</div>}

        <section className="user-panel">
          <header className="user-panel__heading">
            <div><span className="section-label">PENUGASAN AKTIF</span><h2>Daftar mahasiswa dan dosen wali</h2></div>
          </header>

          <form className="user-toolbar advisor-toolbar" onSubmit={applyFilters}>
            <label className="search-field">
              <Search size={19} />
              <input value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Cari nama, NIM, atau email mahasiswa" />
            </label>
            <select value={filters.dosen_id} onChange={(event) => setFilters({ ...filters, dosen_id: event.target.value })}>
              <option value="">Semua dosen wali</option>
              {advisors.map((advisor) => <option key={advisor.id} value={advisor.id}>{advisor.identifier} · {advisor.name}</option>)}
            </select>
            <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
              <option value="">Semua status</option>
              <option value="assigned">Sudah ditetapkan</option>
              <option value="unassigned">Belum ditetapkan</option>
            </select>
            <button className="filter-button" type="submit">Terapkan</button>
            <button className="reset-button" type="button" onClick={resetFilters}><RefreshCw size={16} /> Reset</button>
          </form>

          <div className="user-table-wrap">
            <table className="user-table advisor-table">
              <thead><tr><th>NIM</th><th>Mahasiswa</th><th>Program studi</th><th>Dosen wali</th><th>Aksi</th></tr></thead>
              <tbody>
                {loading && <tr><td colSpan="5" className="table-message">Memuat data penugasan...</td></tr>}
                {!loading && assignments.length === 0 && <tr><td colSpan="5" className="table-message">Belum ada data yang sesuai dengan pencarian.</td></tr>}
                {!loading && assignments.map((item) => (
                  <tr key={item.student_id}>
                    <td><strong className="identifier">{item.identifier}</strong></td>
                    <td><div className="user-name"><span>{item.mahasiswa_name.charAt(0)}</span><div><strong>{item.mahasiswa_name}</strong><small>{item.email}</small></div></div></td>
                    <td>{item.program_studi || '—'}</td>
                    <td>
                      {item.dosen_name
                        ? <div className="advisor-name"><strong>{item.dosen_name}</strong><small>{item.dosen_identifier}</small></div>
                        : <span className="assignment-empty">Belum ditetapkan</span>}
                    </td>
                    <td><button className="assignment-button" onClick={() => openAssignment(item)}><PencilLine size={15} /> {item.dosen_id ? 'Ubah' : 'Tetapkan'}</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <footer className="table-footer">
            <span>{meta.total ? `Menampilkan ${meta.from}–${meta.to} dari ${meta.total} mahasiswa` : 'Tidak ada data'}</span>
            <div>
              <button disabled={meta.current_page <= 1 || loading} onClick={() => { setError(''); setLoading(true); setQuery((current) => ({ ...current, page: current.page - 1 })) }}>Sebelumnya</button>
              <strong>Halaman {meta.current_page} / {meta.last_page}</strong>
              <button disabled={meta.current_page >= meta.last_page || loading} onClick={() => { setError(''); setLoading(true); setQuery((current) => ({ ...current, page: current.page + 1 })) }}>Berikutnya</button>
            </div>
          </footer>
        </section>
      </div>

      {selectedStudent && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setSelectedStudent(null)}>
          <section className="user-modal assignment-modal" role="dialog" aria-modal="true" aria-labelledby="assignment-title">
            <header><div><span className="section-label">PENUGASAN DOSEN</span><h2 id="assignment-title">Atur dosen wali</h2></div><button onClick={() => setSelectedStudent(null)} aria-label="Tutup formulir"><X /></button></header>
            <form onSubmit={saveAssignment}>
              {formError && <div className="form-error">{formError}</div>}
              <div className="selected-student">
                <span>{selectedStudent.mahasiswa_name.charAt(0)}</span>
                <div><strong>{selectedStudent.mahasiswa_name}</strong><small>{selectedStudent.identifier} · {selectedStudent.program_studi}</small></div>
              </div>
              <label className="assignment-select">
                <span>Pilih dosen wali</span>
                <select value={selectedAdvisor} onChange={(event) => setSelectedAdvisor(event.target.value)}>
                  <option value="">Belum ditetapkan</option>
                  {advisors.filter((advisor) => advisor.program_studi === selectedStudent.program_studi).map((advisor) => (
                    <option key={advisor.id} value={advisor.id}>{advisor.name} · {advisor.identifier} · {advisor.student_count} mahasiswa</option>
                  ))}
                </select>
              </label>
              <p className="assignment-note">Hanya dosen aktif dari program studi {selectedStudent.program_studi} yang dapat dipilih. Histori perwalian sebelumnya tetap tersimpan.</p>
              <footer><button type="button" className="modal-cancel" onClick={() => setSelectedStudent(null)}>Batal</button><button className="modal-save" disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan penugasan'}</button></footer>
            </form>
          </section>
        </div>
      )}
    </>
  )
}
