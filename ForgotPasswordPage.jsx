import { useState } from 'react'
import { ArrowLeft, CheckCircle2, Clipboard, MessageCircle, ShieldCheck } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import AuthShell from '../../components/auth/AuthShell'
import { createAdminWhatsAppUrl, hasAdminWhatsApp } from '../../config/support'
import useLanguage from '../../hooks/useLanguage'
import './AuthPages.css'

export default function ForgotPasswordPage() {
  const navigate = useNavigate()
  const { t } = useLanguage()
  const [form, setForm] = useState({ name: '', identifier: '', role: 'mahasiswa', email: '' })
  const [notice, setNotice] = useState('')
  const [requestText, setRequestText] = useState('')

  const buildRequest = () => [
    t.forgot.messageGreeting,
    t.forgot.messageIntro,
    `${t.forgot.name}: ${form.name}`,
    `${t.forgot.identifier}: ${form.identifier}`,
    `${t.forgot.role}: ${t.forgot.roles[form.role]}`,
    `${t.forgot.email}: ${form.email}`,
    t.forgot.messageClosing,
  ].join('\n')

  const submit = async (event) => {
    event.preventDefault()
    const message = buildRequest()
    const whatsappUrl = createAdminWhatsAppUrl(message)
    setRequestText(message)

    if (whatsappUrl) {
      window.open(whatsappUrl, '_blank', 'noopener,noreferrer')
      setNotice(t.forgot.opened)
      return
    }

    try {
      await navigator.clipboard.writeText(message)
      setNotice(t.forgot.copied)
    } catch {
      setNotice(t.forgot.copyFallback)
    }
  }

  return (
    <AuthShell>
      <form className="login-card auth-card auth-card--support" onSubmit={submit}>
        <div className="auth-icon"><ShieldCheck /></div>
        <span className="section-label">{t.forgot.eyebrow}</span>
        <h2>{t.forgot.title}</h2>
        <p className="login-intro">{t.forgot.intro}</p>

        <div className="support-requirements">
          <strong>{t.forgot.requirementsTitle}</strong>
          <ul>{t.forgot.requirements.map((item) => <li key={item}><CheckCircle2 size={15} /> {item}</li>)}</ul>
        </div>

        {notice && <div className="auth-notice">{notice}</div>}

        <label>
          <span className="field-heading"><b>{t.forgot.name}</b></span>
          <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} autoComplete="name" required />
        </label>
        <label>
          <span className="field-heading"><b>{t.forgot.identifier}</b><small>{t.forgot.identityHint}</small></span>
          <input value={form.identifier} onChange={(event) => setForm({ ...form, identifier: event.target.value.toUpperCase() })} autoComplete="username" required />
        </label>
        <label>
          <span className="field-heading"><b>{t.forgot.role}</b></span>
          <select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}>
            <option value="mahasiswa">{t.forgot.roles.mahasiswa}</option>
            <option value="dosen">{t.forgot.roles.dosen}</option>
          </select>
        </label>
        <label>
          <span className="field-heading"><b>{t.forgot.email}</b></span>
          <input type="email" placeholder="nama@gmail.com" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} autoComplete="email" required />
        </label>

        <button className="login-button">
          {hasAdminWhatsApp ? <><MessageCircle size={18} /> {t.forgot.submitChat}</> : <><Clipboard size={18} /> {t.forgot.submitCopy}</>}
        </button>
        {!hasAdminWhatsApp && <small className="support-config-note">{t.forgot.whatsappNote}</small>}
        {requestText && !hasAdminWhatsApp && <textarea className="support-request-preview" value={requestText} readOnly aria-label={t.forgot.previewLabel} />}
        <button className="auth-back" type="button" onClick={() => navigate('/login')}><ArrowLeft size={16} /> {t.forgot.back}</button>
      </form>
    </AuthShell>
  )
}
