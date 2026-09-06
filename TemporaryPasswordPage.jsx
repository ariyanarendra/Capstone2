import { useState } from 'react'
import { ChevronRight, KeyRound, LogOut, ShieldCheck } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import AuthShell from '../../components/auth/AuthShell'
import useLanguage from '../../hooks/useLanguage'
import { api } from '../../services/api'
import './AuthPages.css'

export default function TemporaryPasswordPage({ user, onPasswordChanged, onExit }) {
  const navigate = useNavigate()
  const { t } = useLanguage()
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const submit = async (event) => {
    event.preventDefault()

    if (form.password !== form.password_confirmation) {
      setError(t.temporary.mismatch)
      return
    }

    if (form.password === form.current_password) {
      setError(t.temporary.sameAsTemporary)
      return
    }

    if (!/[A-Za-z]/.test(form.password) || !/\d/.test(form.password)) {
      setError(t.temporary.weakPassword)
      return
    }

    setLoading(true)
    setError('')

    try {
      const response = await api.updateProfilePassword(form)
      onPasswordChanged(response.user)
      navigate(`/${user.role}/dashboard`, { replace: true })
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthShell>
      <form className="login-card auth-card auth-card--temporary" onSubmit={submit}>
        <div className="auth-icon"><KeyRound /></div>
        <span className="section-label">{t.temporary.eyebrow}</span>
        <h2>{t.temporary.title}</h2>
        <p className="login-intro">{t.temporary.intro}</p>

        <div className="temporary-account"><ShieldCheck size={18} /><div><strong>{user.name}</strong><span>{user.identifier}</span></div></div>
        {error && <div className="form-error">{error}</div>}

        <label>
          <span className="field-heading"><b>{t.temporary.currentPassword}</b></span>
          <input type="password" autoComplete="current-password" value={form.current_password} onChange={(event) => setForm({ ...form, current_password: event.target.value })} required />
        </label>
        <label>
          <span className="field-heading"><b>{t.temporary.password}</b></span>
          <input type="password" minLength="8" autoComplete="new-password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} required />
        </label>
        <label>
          <span className="field-heading"><b>{t.temporary.passwordConfirmation}</b></span>
          <input type="password" minLength="8" autoComplete="new-password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} required />
        </label>

        <button className="login-button" disabled={loading}>
          {loading ? t.temporary.loading : <>{t.temporary.submit} <ChevronRight size={19} /></>}
        </button>
        <button className="auth-back" type="button" onClick={onExit} disabled={loading}><LogOut size={17} /> {t.temporary.exit}</button>
      </form>
    </AuthShell>
  )
}
