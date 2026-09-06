import { useCallback, useState } from 'react'
import { ShieldCheck } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import useLanguage from '../../hooks/useLanguage'
import Brand from '../Brand'
import HelpModal from './HelpModal'

export default function AuthShell({ children }) {
  const navigate = useNavigate()
  const { language, setLanguage, t } = useLanguage()
  const [helpOpen, setHelpOpen] = useState(false)
  const closeHelp = useCallback(() => setHelpOpen(false), [])

  const openForgotPassword = () => {
    setHelpOpen(false)
    navigate('/forgot-password')
  }

  return (
    <main className="login-page">
      <header className="login-utility">
        <span>{t.shell.official}</span>
        <div className="login-utility__actions">
          <select className="auth-language-select" aria-label={t.languageLabel} value={language} onChange={(event) => setLanguage(event.target.value)}>
            <option value="id">{t.languages.id}</option>
            <option value="ja">{t.languages.ja}</option>
          </select>
          <button type="button" className="auth-help-trigger" onClick={() => setHelpOpen(true)}>{t.shell.help}</button>
        </div>
      </header>

      <div className="login-layout">
        <section className="login-showcase">
          <div className="login-showcase__image" />
          <div className="login-showcase__overlay" />
          <div className="login-showcase__content">
            <Brand light />
            <div>
              <span className="section-label section-label--light">{t.shell.system}</span>
              <h1>{t.shell.heroTitle}</h1>
              <p>{t.shell.heroDescription}</p>
            </div>
            <p className="campus-address">{t.shell.address}</p>
          </div>
        </section>

        <section className="login-panel">
          <div className="login-panel__brand"><Brand /></div>
          {children}
          <p className="login-security"><ShieldCheck size={16} /> {t.shell.security}</p>
        </section>
      </div>

      <HelpModal open={helpOpen} onClose={closeHelp} onForgotPassword={openForgotPassword} translations={t.help} />
    </main>
  )
}
