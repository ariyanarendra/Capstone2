const adminWhatsApp = (import.meta.env.VITE_ADMIN_WHATSAPP || '').replace(/\D/g, '')

export const hasAdminWhatsApp = adminWhatsApp.length >= 10

export const createAdminWhatsAppUrl = (message) => (
  hasAdminWhatsApp
    ? `https://wa.me/${adminWhatsApp}?text=${encodeURIComponent(message)}`
    : ''
)
