import { useState } from 'react'
import { ChevronRight, GraduationCap, HelpCircle, ShieldCheck, UserRound } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import AuthShell from '../../components/auth/AuthShell'
import { demoAccounts } from '../../config/demoAccounts'
import useLanguage from '../../hooks/useLanguage'
import { api, saveSession } from '../../services/api'
import './AuthPages.css'

export default function LoginPage({ onLogin }) {
  const location = useLocation()
  const navigate = useNavigate()
  const { t } = useLanguage()
  const [form, setForm] = useState({ login: '', password: '' })
  const [remember, setRemember] = useState(false)
  const [loading, setLoading] = useState(false)
  const [demoLoadingRole, setDemoLoadingRole] = useState('')
  const [error, setError] = useState('')

  const completeLogin = async (credentials, rememberSession, demoRole = '') => {
    setLoading(true)
    setDemoLoadingRole(demoRole)
    setError('')

    try {
      const session = await api.login(credentials)
      saveSession(session, rememberSession)
      onLogin(session)
      navigate(`/${session.user.role}/dashboard`, { replace: true })
    } catch (requestError) {
      setError(requestError.message || t.login.invalid)
    } finally {
      setLoading(false)
      setDemoLoadingRole('')
    }
  }

  const submit = (event) => {
    event.preventDefault()
    completeLogin(form, remember)
  }

  const demoIcons = {
    admin: ShieldCheck,
    dosen: UserRound,
    mahasiswa: GraduationCap,
  }

  return (
    <AuthShell>
      <form className="login-card" onSubmit={submit}>
        <span className="section-label">{t.login.eyebrow}</span>
        <h2>{t.login.title}</h2>
        <p className="login-intro">{t.login.intro}</p>

        <section className="demo-login" aria-label={t.login.demoTitle}>
          <div className="demo-login__heading"><strong>{t.login.demoTitle}</strong><span>{t.login.demoIntro}</span></div>
          <div className="demo-login__options">
            {demoAccounts.map((account) => {
              const Icon = demoIcons[account.role]
              return (
                <button key={account.role} type="button" disabled={loading} onClick={() => completeLogin(account, false, account.role)}>
                  <Icon size={18} />
                  <span>{demoLoadingRole === account.role ? t.login.demoLoading : t.login[account.labelKey]}</span>
                </button>
              )
            })}
          </div>
        </section>

        <div className="login-divider"><span>{t.login.manualDivider}</span></div>

        {location.state?.notice && <div className="auth-notice">{location.state.notice}</div>}
        {error && <div className="form-error">{error}</div>}

        <label>
          <span className="field-heading"><b>{t.login.identity}</b><small>{t.login.identityHint}</small></span>
          <input value={form.login} onChange={(event) => setForm({ ...form, login: event.target.value })} placeholder={t.login.identityPlaceholder} autoComplete="username" required />
        </label>

        <label>
          <span className="field-heading"><b>{t.login.password}</b></span>
          <input type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder={t.login.passwordPlaceholder} autoComplete="current-password" required />
        </label>

        <div className="form-options">
          <label className="checkbox"><input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} /> {t.login.remember}</label>
          <button type="button" onClick={() => navigate('/forgot-password')}>{t.login.forgot}</button>
        </div>

        <button className="login-button" disabled={loading}>
          {loading ? t.login.loading : <>{t.login.submit} <ChevronRight size={19} /></>}
        </button>

        <div className="login-support"><HelpCircle size={17} /><span>{t.login.support} <strong>{t.login.administrator}</strong></span></div>
      </form>
    </AuthShell>
  )
}
