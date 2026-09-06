const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'
const SESSION_KEY = 'gatekampus_session'

export const getSession = () => {
  try {
    return JSON.parse(localStorage.getItem(SESSION_KEY) || sessionStorage.getItem(SESSION_KEY))
  } catch {
    return null
  }
}

export const saveSession = (session, remember = true) => {
  localStorage.removeItem(SESSION_KEY)
  sessionStorage.removeItem(SESSION_KEY)
  const storage = remember ? localStorage : sessionStorage
  storage.setItem(SESSION_KEY, JSON.stringify(session))
}

export const updateSessionUser = (user) => {
  const session = getSession()
  if (!session) return null
  const nextSession = { ...session, user }
  const remember = Boolean(localStorage.getItem(SESSION_KEY))
  saveSession(nextSession, remember)
  return nextSession
}

export const clearSession = () => {
  localStorage.removeItem(SESSION_KEY)
  sessionStorage.removeItem(SESSION_KEY)
}

async function request(path, options = {}) {
  const session = getSession()
  const isFormData = options.body instanceof FormData
  let response

  try {
    response = await fetch(`${API_URL}${path}`, {
      ...options,
      headers: {
        ...(!isFormData ? { 'Content-Type': 'application/json' } : {}),
        Accept: 'application/json',
        ...(session?.token ? { Authorization: `Bearer ${session.token}` } : {}),
        ...options.headers,
      },
    })
  } catch {
    throw new Error('Backend belum dapat dihubungi. Pastikan server Laravel sedang berjalan.')
  }

  const data = await response.json().catch(() => ({}))
  if (response.status === 401) {
    clearSession()
    if (window.location.pathname !== '/login') window.location.assign('/login')
    throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.')
  }
  if (response.status === 423 && data.code === 'password_change_required') {
    if (window.location.pathname !== '/change-password') window.location.assign('/change-password')
    throw new Error(data.message)
  }
  if (!response.ok) {
    const error = new Error(data.message || 'Permintaan tidak dapat diproses.')
    error.details = Array.isArray(data.errors)
      ? data.errors
      : Object.values(data.errors || {}).flat()
    throw error
  }
  return data
}

function queryString(params) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) query.set(key, value)
  })
  const value = query.toString()
  return value ? `?${value}` : ''
}

export const api = {
  login: (credentials) => request('/login', { method: 'POST', body: JSON.stringify(credentials) }),
  logout: () => request('/logout', { method: 'POST' }),
  me: () => request('/me'),
  profile: () => request('/profile'),
  updateProfile: (profile) => request('/profile', { method: 'PUT', body: JSON.stringify(profile) }),
  updateProfilePassword: (payload) => request('/profile/password', { method: 'PATCH', body: JSON.stringify(payload) }),
  users: (params = {}) => request(`/users${queryString(params)}`),
  identifierSuggestion: (role, programStudi) => request(`/users/identifier-suggestion${queryString({ role, program_studi: programStudi })}`),
  createUser: (user) => request('/users', { method: 'POST', body: JSON.stringify(user) }),
  updateUser: (userId, user) => request(`/users/${userId}`, { method: 'PUT', body: JSON.stringify(user) }),
  resetUserPassword: (userId, password) => request(`/users/${userId}/password`, {
    method: 'PATCH',
    body: JSON.stringify({ password, password_confirmation: password }),
  }),
  updateUserStatus: (userId, isActive) => request(`/users/${userId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ is_active: isActive }),
  }),
  deleteUser: (userId) => request(`/users/${userId}`, { method: 'DELETE' }),
  adminDashboard: () => request('/admin/dashboard'),
  importUsers: (file, role) => {
    const formData = new FormData()
    formData.append('import_file', file)
    formData.append('role_scope', role)
    return request('/users/import', { method: 'POST', body: formData })
  },
  advisorAssignments: (params = {}) => request(`/advisor-assignments${queryString(params)}`),
  updateAdvisorAssignment: (studentId, dosenId) => request(`/advisor-assignments/${studentId}`, {
    method: 'PUT',
    body: JSON.stringify({ dosen_id: dosenId }),
  }),
  perwalians: (params = {}) => request(`/perwalians${queryString(params)}`),
  createPerwalian: (payload) => request('/perwalians', { method: 'POST', body: JSON.stringify(payload) }),
  updatePerwalian: (perwalianId, payload) => request(`/perwalians/${perwalianId}`, { method: 'PUT', body: JSON.stringify(payload) }),
  cancelPerwalian: (perwalianId, cancellationReason = '') => request(`/perwalians/${perwalianId}/cancel`, {
    method: 'PATCH',
    body: JSON.stringify({ cancellation_reason: cancellationReason }),
  }),
  perwalianReport: (params = {}) => request(`/reports/perwalian${queryString(params)}`),
  dosenDashboard: () => request('/dosen/dashboard'),
  advisorStudents: (params = {}) => request(`/dosen/mahasiswa-wali${queryString(params)}`),
  advisorStudent: (studentId, params = {}) => request(`/dosen/mahasiswa-wali/${studentId}${queryString(params)}`),
  updateDosenPerwalianStatus: (perwalianId, status, catatanDosen) => request(`/dosen/perwalians/${perwalianId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status, catatan_dosen: catatanDosen }),
  }),
  mahasiswaDashboard: () => request('/mahasiswa/dashboard'),
}
