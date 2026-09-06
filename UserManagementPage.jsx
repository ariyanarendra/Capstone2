import { useEffect, useState } from 'react'
import {
  Download,
  KeyRound,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  Upload,
  UserCheck,
  UsersRound,
  UserX,
  X,
} from 'lucide-react'
import { api } from '../../../services/api'
import './UserManagementPage.css'

const roleContent = {
  dosen: {
    title: 'Data Dosen',
    description: 'Kelola akun dosen STMIK Bandung secara terpisah.',
    summaryLabel: 'Total dosen',
    summaryDescription: 'Akun dosen yang memiliki relasi akademik hanya dapat dinonaktifkan.',
    listTitle: 'Daftar dosen',
    addLabel: 'Tambah dosen',
    identityLabel: 'Kode Dosen',
    searchPlaceholder: 'Cari nama, email, atau NIP dosen',
    emptyMessage: 'Belum ada dosen yang sesuai dengan pencarian.',
    excelTemplate: '/downloads/master-import-dosen-gatekampus.xlsx',
    excelFilename: 'Master Import Dosen GateKampus.xlsx',
  },
  mahasiswa: {
    title: 'Data Mahasiswa',
    description: 'Kelola akun mahasiswa STMIK Bandung secara terpisah.',
    summaryLabel: 'Total mahasiswa',
    summaryDescription: 'Akun mahasiswa yang memiliki riwayat akademik hanya dapat dinonaktifkan.',
    listTitle: 'Daftar mahasiswa',
    addLabel: 'Tambah mahasiswa',
    identityLabel: 'NIM',
    searchPlaceholder: 'Cari nama, email, atau NIM mahasiswa',
    emptyMessage: 'Belum ada mahasiswa yang sesuai dengan pencarian.',
    excelTemplate: '/downloads/master-import-mahasiswa-gatekampus.xlsx',
    excelFilename: 'Master Import Mahasiswa GateKampus.xlsx',
  },
}

const createEmptyUserForm = (role) => ({
  name: '',
  email: '',
  identifier: '',
  role,
  program_studi: 'Informatika',
  password: '',
})

const initialMeta = { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }
const strongPasswordPattern = '(?=.*[A-Za-z])(?=.*\\d).{8,}'

const hasValidTemporaryPassword = (password) => (
  password.length >= 8 && /[A-Za-z]/.test(password) && /\d/.test(password)
)

function ErrorDetails({ message, details = [] }) {
  if (!message) return null

  return (
    <div className="form-error">
      <strong>{message}</strong>
      {details.length > 0 && <ul>{details.map((detail) => <li key={detail}>{detail}</li>)}</ul>}
    </div>
  )
}

export default function UserManagementPage({ role }) {
  const content = roleContent[role]
  const [users, setUsers] = useState([])
  const [meta, setMeta] = useState(initialMeta)
  const [filters, setFilters] = useState({ search: '', program_studi: '', status: '' })
  const [query, setQuery] = useState({ search: '', role, program_studi: '', status: '', page: 1 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [reloadKey, setReloadKey] = useState(0)

  const [showForm, setShowForm] = useState(false)
  const [editingUser, setEditingUser] = useState(null)
  const [form, setForm] = useState(createEmptyUserForm(role))
  const [formError, setFormError] = useState('')
  const [formDetails, setFormDetails] = useState([])
  const [saving, setSaving] = useState(false)
  const [identifierLoading, setIdentifierLoading] = useState(false)

  const [passwordUser, setPasswordUser] = useState(null)
  const [newPassword, setNewPassword] = useState('')
  const [passwordError, setPasswordError] = useState('')

  const [showImport, setShowImport] = useState(false)
  const [importFile, setImportFile] = useState(null)
  const [importError, setImportError] = useState('')
  const [importDetails, setImportDetails] = useState([])

  const [confirmation, setConfirmation] = useState(null)
  const [actionError, setActionError] = useState('')
  const [actionSaving, setActionSaving] = useState(false)

  useEffect(() => {
    let active = true

    api.users(query)
      .then((response) => {
        if (!active) return
        setUsers(response.data || [])
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

  const refreshUsers = (message) => {
    setNotice(message)
    setError('')
    setLoading(true)
    setReloadKey((value) => value + 1)
  }

  const applyFilters = (event) => {
    event.preventDefault()
    setNotice('')
    setError('')
    setLoading(true)
    setQuery({ ...filters, role, search: filters.search.trim(), page: 1 })
  }

  const resetFilters = () => {
    const emptyFilters = { search: '', program_studi: '', status: '' }
    setFilters(emptyFilters)
    setNotice('')
    setError('')
    setLoading(true)
    setQuery({ ...emptyFilters, role, page: 1 })
  }

  const loadIdentifierSuggestion = async (programStudi) => {
    setIdentifierLoading(true)

    try {
      const response = await api.identifierSuggestion(role, programStudi)
      setForm((current) => ({ ...current, identifier: response.identifier }))
    } catch (requestError) {
      setFormError(requestError.message)
    } finally {
      setIdentifierLoading(false)
    }
  }

  const openCreateForm = () => {
    setEditingUser(null)
    const emptyForm = createEmptyUserForm(role)
    setForm(emptyForm)
    setFormError('')
    setFormDetails([])
    setShowForm(true)
    loadIdentifierSuggestion(emptyForm.program_studi)
  }

  const openEditForm = (user) => {
    setEditingUser(user)
    setForm({
      name: user.name,
      email: user.email,
      identifier: user.identifier,
      role: user.role,
      program_studi: user.program_studi || '',
      password: '',
    })
    setFormError('')
    setFormDetails([])
    setShowForm(true)
  }

  const changeProgramStudy = (programStudi) => {
    setForm((current) => ({ ...current, program_studi: programStudi }))
    if (!editingUser && programStudi) loadIdentifierSuggestion(programStudi)
  }

  const saveUser = async (event) => {
    event.preventDefault()

    if (!editingUser && !hasValidTemporaryPassword(form.password)) {
      setFormError('Kata sandi sementara minimal 8 karakter serta wajib mengandung huruf dan angka.')
      setFormDetails([])
      return
    }

    setSaving(true)
    setFormError('')
    setFormDetails([])

    try {
      const response = editingUser
        ? await api.updateUser(editingUser.id, {
          name: form.name,
          email: form.email,
          identifier: form.identifier,
          role: form.role,
          program_studi: form.program_studi,
        })
        : await api.createUser(form)

      setShowForm(false)
      setQuery((current) => ({ ...current, page: editingUser ? current.page : 1 }))
      refreshUsers(response.message)
    } catch (requestError) {
      setFormError(requestError.message)
      setFormDetails(requestError.details || [])
    } finally {
      setSaving(false)
    }
  }

  const openPasswordForm = (user) => {
    setPasswordUser(user)
    setNewPassword('')
    setPasswordError('')
  }

  const resetPassword = async (event) => {
    event.preventDefault()

    if (!hasValidTemporaryPassword(newPassword)) {
      setPasswordError('Kata sandi sementara minimal 8 karakter serta wajib mengandung huruf dan angka.')
      return
    }

    setActionSaving(true)
    setPasswordError('')

    try {
      const response = await api.resetUserPassword(passwordUser.id, newPassword)
      setPasswordUser(null)
      setNewPassword('')
      refreshUsers(response.message)
    } catch (requestError) {
      setPasswordError(requestError.message)
    } finally {
      setActionSaving(false)
    }
  }

  const openImportForm = () => {
    setImportFile(null)
    setImportError('')
    setImportDetails([])
    setShowImport(true)
  }

  const importUsers = async (event) => {
    event.preventDefault()

    if (!importFile) {
      setImportError('Pilih file CSV terlebih dahulu.')
      return
    }

    setActionSaving(true)
    setImportError('')
    setImportDetails([])

    try {
      const response = await api.importUsers(importFile, role)
      setShowImport(false)
      setQuery((current) => ({ ...current, page: 1 }))
      refreshUsers(response.message)
    } catch (requestError) {
      setImportError(requestError.message)
      setImportDetails(requestError.details || [])
    } finally {
      setActionSaving(false)
    }
  }

  const downloadTemplate = () => {
    const link = document.createElement('a')
    link.href = content.excelTemplate
    link.download = content.excelFilename
    document.body.appendChild(link)
    link.click()
    link.remove()
  }

  const runConfirmedAction = async () => {
    setActionSaving(true)
    setActionError('')

    try {
      const response = confirmation.type === 'delete'
        ? await api.deleteUser(confirmation.user.id)
        : await api.updateUserStatus(confirmation.user.id, !confirmation.user.is_active)

      setConfirmation(null)
      refreshUsers(response.message)
    } catch (requestError) {
      setActionError(requestError.message)
    } finally {
      setActionSaving(false)
    }
  }

  const changePage = (page) => {
    setError('')
    setLoading(true)
    setQuery((current) => ({ ...current, page }))
  }

  return (
    <>
      <section className="page-banner page-banner--users">
        <div className="page-banner__inner">
          <div>
            <span className="section-label section-label--light">ADMINISTRASI PORTAL</span>
            <h1>{content.title}</h1>
            <p>{content.description}</p>
          </div>
          <UsersRound aria-hidden="true" />
        </div>
      </section>

      <div className="portal-content user-content">
        <section className="user-summary">
          <div><span>{content.summaryLabel}</span><strong>{meta.total}</strong></div>
          <p>{content.summaryDescription}</p>
          <div className="user-summary__actions">
            <button className="secondary-action" onClick={openImportForm}><Upload size={18} /> Impor CSV</button>
            <button onClick={openCreateForm}><Plus size={18} /> {content.addLabel}</button>
          </div>
        </section>

        {notice && <div className="page-notice page-notice--success">{notice}</div>}
        {error && <div className="page-notice page-notice--error">{error}</div>}

        <section className="user-panel">
          <header className="user-panel__heading">
            <div><span className="section-label">DAFTAR AKUN</span><h2>{content.listTitle}</h2></div>
          </header>

          <form className={`user-toolbar user-toolbar--scoped ${role === 'mahasiswa' ? 'user-toolbar--with-program' : ''}`} onSubmit={applyFilters}>
            <label className="search-field">
              <Search size={19} />
              <input value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder={content.searchPlaceholder} />
            </label>
            {role === 'mahasiswa' && (
              <select aria-label="Filter program studi" value={filters.program_studi} onChange={(event) => setFilters({ ...filters, program_studi: event.target.value })}>
                <option value="">Semua program studi</option>
                <option value="Informatika">Informatika</option>
                <option value="Sistem Informasi">Sistem Informasi</option>
              </select>
            )}
            <select aria-label="Filter status" value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
              <option value="">Semua status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
            <button className="filter-button" type="submit">Terapkan</button>
            <button className="reset-button" type="button" onClick={resetFilters}><RefreshCw size={16} /> Reset</button>
          </form>

          <div className="user-table-wrap">
            <table className="user-table user-table--actions">
              <thead>
                <tr><th>{content.identityLabel}</th><th>Nama pengguna</th><th>Email Gmail</th><th>Program studi</th><th>Status</th><th>Tindakan</th></tr>
              </thead>
              <tbody>
                {loading && <tr><td colSpan="6" className="table-message">Memuat {content.title.toLowerCase()}...</td></tr>}
                {!loading && users.length === 0 && <tr><td colSpan="6" className="table-message">{content.emptyMessage}</td></tr>}
                {!loading && users.map((item) => (
                  <tr key={item.id}>
                    <td><strong className="identifier">{item.identifier}</strong></td>
                    <td>
                      <div className="user-name">
                        <span>{item.name.charAt(0)}</span>
                        <div><strong>{item.name}</strong>{item.is_current_user && <small>Akun Anda</small>}</div>
                      </div>
                    </td>
                    <td>{item.email}</td>
                    <td>{item.program_studi || '—'}</td>
                    <td><span className={`account-status account-status--${item.is_active ? 'active' : 'inactive'}`}>{item.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                    <td>
                      <div className="user-row-actions">
                        <button onClick={() => openEditForm(item)} title="Ubah data pengguna"><Pencil size={15} /> Ubah</button>
                        <button onClick={() => openPasswordForm(item)} title="Buat kata sandi sementara"><KeyRound size={15} /> Password</button>
                        <button
                          className={item.is_active ? 'action-warning' : 'action-success'}
                          disabled={item.is_current_user && item.is_active}
                          onClick={() => { setActionError(''); setConfirmation({ type: 'status', user: item }) }}
                          title={item.is_current_user ? 'Akun yang sedang digunakan tidak dapat dinonaktifkan' : item.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'}
                        >
                          {item.is_active ? <UserX size={15} /> : <UserCheck size={15} />}
                          {item.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                        </button>
                        {item.can_delete
                          ? <button className="action-danger" onClick={() => { setActionError(''); setConfirmation({ type: 'delete', user: item }) }} title="Hapus akun salah input"><Trash2 size={15} /> Hapus</button>
                          : <small className="protected-record">Riwayat terlindungi</small>}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <footer className="table-footer">
            <span>{meta.total ? `Menampilkan ${meta.from}–${meta.to} dari ${meta.total} ${role}` : 'Tidak ada data'}</span>
            <div>
              <button disabled={meta.current_page <= 1 || loading} onClick={() => changePage(meta.current_page - 1)}>Sebelumnya</button>
              <strong>Halaman {meta.current_page} / {meta.last_page}</strong>
              <button disabled={meta.current_page >= meta.last_page || loading} onClick={() => changePage(meta.current_page + 1)}>Berikutnya</button>
            </div>
          </footer>
        </section>
      </div>

      {showForm && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setShowForm(false)}>
          <section className="user-modal" role="dialog" aria-modal="true" aria-labelledby="user-form-title">
            <header>
              <div><span className="section-label">{editingUser ? 'UBAH AKUN' : 'AKUN BARU'}</span><h2 id="user-form-title">{editingUser ? `Ubah data ${role}` : content.addLabel}</h2></div>
              <button onClick={() => setShowForm(false)} aria-label="Tutup formulir"><X /></button>
            </header>
            <form onSubmit={saveUser} autoComplete="off">
              <ErrorDetails message={formError} details={formDetails} />
              <div className="form-grid">
                <label><span>Nama lengkap</span><input autoComplete="off" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required /></label>
                <label><span>{content.identityLabel}</span><input autoComplete="off" value={identifierLoading ? 'Memuat rekomendasi...' : form.identifier} readOnly required /><small className="field-note">{editingUser ? `${content.identityLabel} tidak dapat diubah setelah akun dibuat.` : 'Nomor berikutnya direkomendasikan otomatis oleh sistem.'}</small></label>
                <label className="form-grid__wide"><span>Email Gmail</span><input autoComplete="off" type="email" placeholder="nama@gmail.com" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} required /></label>
                <label><span>Program studi</span><select value={form.program_studi} onChange={(event) => changeProgramStudy(event.target.value)} required><option value="">Pilih program studi</option><option value="Informatika">Informatika</option><option value="Sistem Informasi">Sistem Informasi</option></select></label>
                {!editingUser && <label className="form-grid__wide"><span>Kata sandi sementara</span><input autoComplete="new-password" type="password" minLength="8" pattern={strongPasswordPattern} title="Minimal 8 karakter serta mengandung huruf dan angka" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder="Minimal 8 karakter, huruf dan angka" required /><small className="field-note">Pengguna wajib menggantinya setelah login pertama.</small></label>}
              </div>
              <footer><button type="button" className="modal-cancel" onClick={() => setShowForm(false)}>Batal</button><button className="modal-save" disabled={saving || identifierLoading}>{saving ? 'Menyimpan...' : editingUser ? 'Simpan perubahan' : 'Simpan pengguna'}</button></footer>
            </form>
          </section>
        </div>
      )}

      {passwordUser && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setPasswordUser(null)}>
          <section className="user-modal user-modal--small" role="dialog" aria-modal="true" aria-labelledby="password-title">
            <header><div><span className="section-label">KEAMANAN AKUN</span><h2 id="password-title">Buat kata sandi sementara</h2></div><button onClick={() => setPasswordUser(null)} aria-label="Tutup formulir"><X /></button></header>
            <form onSubmit={resetPassword} autoComplete="off">
              <p className="modal-description">Buat kata sandi sementara untuk <strong>{passwordUser.name}</strong>. Sesi lama akan diputus dan pengguna wajib membuat kata sandi pribadi setelah login.</p>
              <ErrorDetails message={passwordError} />
              <div className="form-grid form-grid--single">
                <label><span>Kata sandi sementara</span><input autoComplete="new-password" type="password" minLength="8" pattern={strongPasswordPattern} title="Minimal 8 karakter serta mengandung huruf dan angka" value={newPassword} onChange={(event) => setNewPassword(event.target.value)} placeholder="Minimal 8 karakter, huruf dan angka" required /><small className="field-note">Sampaikan hanya kepada pemilik akun setelah identitasnya diverifikasi.</small></label>
              </div>
              <footer><button type="button" className="modal-cancel" onClick={() => setPasswordUser(null)}>Batal</button><button className="modal-save" disabled={actionSaving}>{actionSaving ? 'Menyimpan...' : 'Buat password sementara'}</button></footer>
            </form>
          </section>
        </div>
      )}

      {showImport && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setShowImport(false)}>
          <section className="user-modal user-modal--import" role="dialog" aria-modal="true" aria-labelledby="import-title">
            <header><div><span className="section-label">IMPORT DATA</span><h2 id="import-title">Impor {role} dari CSV</h2></div><button onClick={() => setShowImport(false)} aria-label="Tutup formulir"><X /></button></header>
            <form onSubmit={importUsers}>
              <button className="template-button" type="button" onClick={downloadTemplate}><Download size={17} /> Unduh Excel Master</button>
              <p className="import-format-note"><strong>Saat Save As:</strong> pilih <b>CSV UTF-8 (Comma delimited) (*.csv)</b>. Excel mungkin memperingatkan bahwa format tabel akan hilang; pilih lanjutkan karena file CSV memang dipakai untuk proses impor.</p>
              <ErrorDetails message={importError} details={importDetails} />
              <label className="csv-upload">
                <Upload size={25} />
                <span>{importFile ? importFile.name : 'Pilih file CSV'}</span>
                <small>Maksimal 2 MB</small>
                <input type="file" accept=".csv,text/csv" onChange={(event) => setImportFile(event.target.files?.[0] || null)} />
              </label>
              <footer><button type="button" className="modal-cancel" onClick={() => setShowImport(false)}>Batal</button><button className="modal-save" disabled={actionSaving}>{actionSaving ? 'Mengimpor...' : 'Impor pengguna'}</button></footer>
            </form>
          </section>
        </div>
      )}

      {confirmation && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setConfirmation(null)}>
          <section className="user-modal user-modal--confirm" role="alertdialog" aria-modal="true" aria-labelledby="confirmation-title">
            <header><div><span className="section-label">KONFIRMASI</span><h2 id="confirmation-title">{confirmation.type === 'delete' ? 'Hapus akun permanen?' : confirmation.user.is_active ? 'Nonaktifkan akun?' : 'Aktifkan akun?'}</h2></div><button onClick={() => setConfirmation(null)} aria-label="Tutup konfirmasi"><X /></button></header>
            <div className="confirmation-body">
              {confirmation.type === 'delete'
                ? <p>Akun <strong>{confirmation.user.name}</strong> akan dihapus permanen. Gunakan ini hanya untuk akun salah input yang belum memiliki data akademik.</p>
                : <p>Akun <strong>{confirmation.user.name}</strong> akan {confirmation.user.is_active ? 'dinonaktifkan dan tidak dapat login' : 'diaktifkan kembali'}.</p>}
              <ErrorDetails message={actionError} />
              <footer><button type="button" className="modal-cancel" onClick={() => setConfirmation(null)}>Batal</button><button className={confirmation.type === 'delete' ? 'modal-danger' : 'modal-save'} disabled={actionSaving} onClick={runConfirmedAction}>{actionSaving ? 'Memproses...' : 'Ya, lanjutkan'}</button></footer>
            </div>
          </section>
        </div>
      )}
    </>
  )
}
