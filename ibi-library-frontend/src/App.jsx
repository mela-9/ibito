import { useEffect, useState } from 'react'
import { NavLink, Navigate, Outlet, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import api, { getErrorMessage } from './services/api'
import {
  BookDetailPage,
  DashboardPage,
  BookshelfPage,
  HomePage,
  LibrarianCollectionsPage,
  LibrarianMembersPage,
  NotificationsPage,
  SearchPage,
} from './pages/LibraryPages'
import AntreanSaya from './pages/AntreanSaya'
import EBookReader from './pages/EBookReader'
import './App.css'

const memberLinks = [
  { to: '/home', label: 'Beranda', icon: '⌂' },
  { to: '/search', label: 'Jelajahi buku', icon: '⌕' },
  { to: '/shelf', label: 'Rak buku', icon: '▤' },
  { to: '/queue', label: 'Antrean saya', icon: '◷' },
  { to: '/notifications', label: 'Notifikasi', icon: '♧' },
]

const librarianLinks = [
  { to: '/librarian', label: 'Ringkasan', icon: '▦', end: true },
  { to: '/librarian/collections', label: 'Kelola koleksi', icon: '▤' },
  { to: '/librarian/members', label: 'Verifikasi anggota', icon: '♙' },
  { to: '/librarian/notifications', label: 'Notifikasi', icon: '♧' },
]

function getHomePath(user) {
  return user?.peran === 'pustakawan' ? '/librarian' : '/home'
}

function LoginPage({ onLogin }) {
  const [mode, setMode] = useState('login')
  const [form, setForm] = useState({ nama: '', email: '', kata_sandi: '' })
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(event) {
    event.preventDefault()
    setError('')
    setMessage('')
    setBusy(true)
    try {
      if (mode === 'register') {
        const { data } = await api.post('/register', form)
        setMessage(data.message)
        setMode('login')
      } else {
        const { data } = await api.post('/login', {
          email: form.email,
          kata_sandi: form.kata_sandi,
        })
        localStorage.setItem('ibi-token', data.token)
        onLogin(data.user)
      }
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="auth-page">
      <section className="auth-card">
        <div className="brand-mark">i</div>
        <p className="eyebrow">RUANG BACA DIGITAL</p>
        <h1>{mode === 'login' ? 'Selamat datang kembali' : 'Mulai perjalanan membaca'}</h1>
        <p className="muted">Masuk dengan akun Anda. Portal akan dibuka sesuai peran anggota atau pustakawan.</p>
        <div className="auth-tabs">
          <button className={mode === 'login' ? 'selected' : ''} onClick={() => setMode('login')}>Masuk</button>
          <button className={mode === 'register' ? 'selected' : ''} onClick={() => setMode('register')}>Daftar anggota</button>
        </div>
        <form className="form-stack" onSubmit={submit}>
          {mode === 'register' && (
            <label>Nama lengkap
              <input required value={form.nama} onChange={(event) => setForm({ ...form, nama: event.target.value })} placeholder="Nama Anda" />
            </label>
          )}
          <label>Email
            <input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} placeholder="nama@email.com" />
          </label>
          <label>Kata sandi
            <input required minLength={8} type="password" value={form.kata_sandi} onChange={(event) => setForm({ ...form, kata_sandi: event.target.value })} placeholder="Minimal 8 karakter" />
          </label>
          {message && <p className="notice success">{message}</p>}
          {error && <p className="notice error">{error}</p>}
          <button className="button primary full" disabled={busy}>{busy ? 'Memproses…' : mode === 'login' ? 'Masuk ke portal' : 'Buat akun anggota'}</button>
        </form>
        <p className="auth-footnote">Pendaftaran hanya untuk anggota. Akun anggota perlu diverifikasi pustakawan.</p>
      </section>
    </main>
  )
}

function useUnreadNotifications() {
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    let active = true
    async function refresh() {
      try {
        const { data } = await api.get('/notifikasi')
        if (active) setUnread(data.filter((notification) => !notification.status_baca).length)
      } catch {
        if (active) setUnread(0)
      }
    }
    refresh()
    const timer = window.setInterval(refresh, 30000)
    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [])

  return unread
}

function MemberLayout({ user, onLogout }) {
  const navigate = useNavigate()
  const location = useLocation()
  const unread = useUnreadNotifications()
  const currentLabel = memberLinks.find((link) => link.to === location.pathname)?.label || 'Detail buku'

  return (
    <div className="app-shell member-shell">
      <aside className="sidebar member-sidebar">
        <button className="brand" onClick={() => navigate('/home')}>
          <span className="brand-mark small">i</span><span>iBI <b>Library</b></span>
        </button>
        <p className="nav-heading">RUANG BACA</p>
        <nav className="side-nav">
          {memberLinks.map((link) => (
            <NavLink key={link.to} to={link.to} className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <span className="nav-icon">{link.icon}</span>{link.label}
              {link.to === '/notifications' && unread > 0 && <span className="nav-count">{unread}</span>}
            </NavLink>
          ))}
        </nav>
        <div className="profile-row">
          <div className="avatar">{user.nama?.slice(0, 1).toUpperCase()}</div>
          <div className="profile-meta"><b>{user.nama}</b><span>Anggota</span></div>
          <button className="logout-button" onClick={onLogout} aria-label="Keluar" title="Keluar">↪</button>
        </div>
      </aside>
      <main className="main-panel member-main">
        <header className="topbar">
          <div className="breadcrumb">Ruang baca <span>/</span> <b>{currentLabel}</b></div>
          <div className="role-topbar-actions">
            <button className="topbar-notice" onClick={() => navigate('/notifications')} aria-label="Buka notifikasi">♧ {unread > 0 && <i>{unread}</i>}</button>
            <button className="mobile-logout" onClick={onLogout} aria-label="Keluar" title="Keluar">↪</button>
          </div>
        </header>
        <div className="page-content"><Outlet /></div>
      </main>
    </div>
  )
}

function LibrarianLayout({ user, onLogout }) {
  const navigate = useNavigate()
  const location = useLocation()
  const unread = useUnreadNotifications()
  const currentLink = librarianLinks.find((link) => link.to === location.pathname)

  return (
    <div className="app-shell admin-shell">
      <aside className="admin-sidebar">
        <button className="admin-brand" onClick={() => navigate('/librarian')}>
          <span className="brand-mark admin-mark">i</span>
          <span><b>iBI Library</b><small>RUANG PUSTAKAWAN</small></span>
        </button>
        <div className="admin-workspace"><span className="workspace-icon">⌂</span><span><b>Perpustakaan iBI</b><small>Panel pengelolaan</small></span><i>⌄</i></div>
        <p className="admin-nav-heading">PENGELOLAAN</p>
        <nav className="side-nav admin-nav">
          {librarianLinks.map((link) => (
            <NavLink key={link.to} to={link.to} end={link.end} className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <span className="nav-icon">{link.icon}</span>{link.label}
              {link.to === '/librarian/notifications' && unread > 0 && <span className="nav-count">{unread}</span>}
            </NavLink>
          ))}
        </nav>
        <div className="admin-sidebar-note">
          <span className="admin-note-spark">✦</span>
          <p>Setiap buku membuka ruang baru untuk belajar.</p>
        </div>
        <div className="admin-profile">
          <div className="avatar admin-avatar">{user.nama?.slice(0, 1).toUpperCase()}</div>
          <div className="profile-meta"><b>{user.nama}</b><span>Pustakawan</span></div>
          <button className="logout-button admin-logout" onClick={onLogout} aria-label="Keluar" title="Keluar">↪</button>
        </div>
      </aside>
      <main className="admin-main">
        <header className="admin-topbar">
          <div><span className="admin-topbar-kicker">iBI LIBRARY / ADMINISTRASI</span><b>{currentLink?.label || 'Pustakawan'}</b></div>
          <button className="admin-topbar-notice" onClick={() => navigate('/librarian/notifications')} aria-label="Buka notifikasi">
            ♧ <span>Notifikasi</span>{unread > 0 && <i>{unread}</i>}
          </button>
          <button className="mobile-logout admin-mobile-logout" onClick={onLogout} aria-label="Keluar" title="Keluar">↪</button>
        </header>
        <div className="admin-content"><Outlet /></div>
      </main>
    </div>
  )
}

export default function App() {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(Boolean(localStorage.getItem('ibi-token')))
  const homePath = getHomePath(user)

  useEffect(() => {
    if (!localStorage.getItem('ibi-token')) return
    api.get('/me')
      .then(({ data }) => setUser(data.user))
      .catch(() => localStorage.removeItem('ibi-token'))
      .finally(() => setLoading(false))
  }, [])

  function logout() {
    api.post('/logout').catch(() => {})
    localStorage.removeItem('ibi-token')
    setUser(null)
  }

  if (loading) return <div className="loading-screen">Menyiapkan ruang baca…</div>

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to={homePath} replace /> : <LoginPage onLogin={setUser} />} />
      <Route element={user?.peran === 'anggota' ? <MemberLayout user={user} onLogout={logout} /> : <Navigate to={user ? homePath : '/login'} replace />}>
        <Route path="/home" element={<HomePage />} />
        <Route path="/shelf" element={<BookshelfPage />} />
        <Route path="/search" element={<SearchPage />} />
        <Route path="/books/:bookId" element={<BookDetailPage />} />
        <Route path="/queue" element={<AntreanSaya />} />
        <Route path="/reader/:loanId" element={<EBookReader />} />
        <Route path="/notifications" element={<NotificationsPage />} />
      </Route>
      <Route element={user?.peran === 'pustakawan' ? <LibrarianLayout user={user} onLogout={logout} /> : <Navigate to={user ? homePath : '/login'} replace />}>
        <Route path="/librarian" element={<DashboardPage />} />
        <Route path="/librarian/collections" element={<LibrarianCollectionsPage />} />
        <Route path="/librarian/books/:bookId" element={<BookDetailPage />} />
        <Route path="/librarian/members" element={<LibrarianMembersPage />} />
        <Route path="/librarian/notifications" element={<NotificationsPage audience="librarian" />} />
      </Route>
      <Route path="/" element={<Navigate to={homePath} replace />} />
      <Route path="*" element={<Navigate to={homePath} replace />} />
    </Routes>
  )
}
