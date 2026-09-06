import { useEffect } from 'react'
import { HelpCircle, KeyRound, X } from 'lucide-react'
import './HelpModal.css'

export default function HelpModal({ open, onClose, onForgotPassword, translations }) {
  useEffect(() => {
    if (!open) return undefined

    const closeOnEscape = (event) => {
      if (event.key === 'Escape') onClose()
    }

    document.addEventListener('keydown', closeOnEscape)
    return () => document.removeEventListener('keydown', closeOnEscape)
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="help-modal" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section className="help-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="help-modal-title">
        <header>
          <div className="help-modal__icon"><HelpCircle /></div>
          <div>
            <span className="section-label">{translations.eyebrow}</span>
            <h2 id="help-modal-title">{translations.title}</h2>
          </div>
          <button type="button" className="help-modal__close" onClick={onClose} aria-label={translations.close}><X /></button>
        </header>

        <p>{translations.intro}</p>
        <ol>{translations.steps.map((step) => <li key={step}>{step}</li>)}</ol>

        <footer>
          <button type="button" className="help-modal__secondary" onClick={onClose}>{translations.close}</button>
          <button type="button" className="help-modal__primary" onClick={onForgotPassword}><KeyRound size={17} /> {translations.forgot}</button>
        </footer>
      </section>
    </div>
  )
}
