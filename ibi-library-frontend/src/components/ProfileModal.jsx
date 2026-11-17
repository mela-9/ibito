import { useEffect, useState } from 'react'
import api, { getErrorMessage } from '../services/api'

export default function ProfileModal({ isOpen, onClose, user, onUserUpdated }) {
  const [name, setName] = useState(user?.nama || '')
  const [nameBusy, setNameBusy] = useState(false)
  const [nameSuccess, setNameSuccess] = useState('')
  const [nameError, setNameError] = useState('')

  // Password change states
  const [requestBusy, setRequestBusy] = useState(false)
  const [otpSent, setOtpSent] = useState(false)
  const [otpInput, setOtpInput] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [passwordBusy, setPasswordBusy] = useState(false)
  const [passwordSuccess, setPasswordSuccess] = useState('')
  const [passwordError, setPasswordError] = useState('')

  // Simulated email popup state
  const [simulatedEmail, setSimulatedEmail] = useState(null)
  const [copiedOtp, setCopiedOtp] = useState(false)

  useEffect(() => {
    if (user?.nama) setName(user.nama)
  }, [user])

  useEffect(() => {
    if (!isOpen) return undefined
    function handleKeyDown(e) {
      if (e.key === 'Escape') {
        if (simulatedEmail) {
          setSimulatedEmail(null)
        } else {
          onClose()
        }
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [isOpen, simulatedEmail, onClose])

  if (!isOpen) return null

  async function handleSaveName(e) {
    e.preventDefault()
    if (!name.trim()) return
    setNameBusy(true)
    setNameSuccess('')
    setNameError('')
    try {
      const { data } = await api.put('/profil', { nama: name.trim() })
      setNameSuccess('Nama berhasil diperbarui di database.')
      if (onUserUpdated) onUserUpdated(data.user)
    } catch (err) {
      setNameError(getErrorMessage(err))
    } finally {
      setNameBusy(false)
    }
  }

  async function handleRequestOtp() {
    setRequestBusy(true)
    setPasswordError('')
    setPasswordSuccess('')
    try {
      const { data } = await api.post('/profil/minta-otp-sandi')
      setOtpSent(true)
      setSimulatedEmail({
        otp: data.otp,
        email: data.email || user.email,
        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      })
    } catch (err) {
      setPasswordError(getErrorMessage(err))
    } finally {
      setRequestBusy(false)
    }
  }

  async function handleChangePassword(e) {
    e.preventDefault()
    setPasswordError('')
    setPasswordSuccess('')

    if (newPassword.length < 8) {
      setPasswordError('Kata sandi baru minimal 8 karakter.')
      return
    }
    if (newPassword !== confirmPassword) {
      setPasswordError('Konfirmasi kata sandi tidak cocok dengan kata sandi baru.')
      return
    }
    if (!otpInput.trim()) {
      setPasswordError('Masukkan kode OTP verifikasi.')
      return
    }

    setPasswordBusy(true)
    try {
      const { data } = await api.post('/profil/ubah-sandi', {
        otp: otpInput.trim(),
        kata_sandi: newPassword,
      })
      setPasswordSuccess(data.message || 'Kata sandi berhasil diperbarui di database!')
      setOtpInput('')
      setNewPassword('')
      setConfirmPassword('')
      setOtpSent(false)
      setSimulatedEmail(null)
    } catch (err) {
      setPasswordError(getErrorMessage(err))
    } finally {
      setPasswordBusy(false)
    }
  }

  function handleCopyOtp(code) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(code)
      setCopiedOtp(true)
      setTimeout(() => setCopiedOtp(false), 2000)
    }
  }

  function handleUseOtp(code) {
    setOtpInput(code)
    setSimulatedEmail(null)
  }

  return (
    <div className="dialog-backdrop" onClick={onClose}>
      <section
        className="queue-dialog profile-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-dialog-title"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="queue-dialog-header">
          <div>
            <p className="eyebrow">PENGATURAN AKUN</p>
            <h2 id="profile-dialog-title">Profil Pengguna</h2>
            <p className="muted">Kelola identitas dan keamanan kata sandi Anda.</p>
          </div>
          <button className="icon-action" aria-label="Tutup" onClick={onClose}>×</button>
        </header>

        <div className="profile-badge-row">
          <div className="profile-big-avatar">{user.nama?.slice(0, 1).toUpperCase()}</div>
          <div className="profile-badge-meta">
            <b>{user.nama}</b>
            <span className="profile-role-tag">{user.peran === 'pustakawan' ? 'Pustakawan' : 'Anggota Perpustakaan'}</span>
            <small>Terdaftar: {user.email}</small>
          </div>
        </div>

        <div className="profile-sections">
          {/* Section 1: Ubah Nama & Info Email */}
          <section className="profile-card-section">
            <h3 className="profile-section-title">Informasi Akun</h3>

            <div className="profile-input-group">
              <label>Email terdaftar
                <input
                  type="email"
                  value={user.email}
                  disabled
                  readOnly
                  className="profile-readonly-input"
                  title="Email akun yang digunakan"
                />
              </label>
              <small className="field-hint">Email akun bersifat permanen dan digunakan untuk verifikasi autentikasi.</small>
            </div>

            <form onSubmit={handleSaveName} className="profile-input-group">
              <label htmlFor="user-name-input">Nama lengkap
                <div className="profile-name-action-row">
                  <input
                    id="user-name-input"
                    type="text"
                    required
                    maxLength={255}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Nama lengkap Anda"
                  />
                  <button
                    type="submit"
                    className="button primary profile-save-btn"
                    disabled={nameBusy || name.trim() === user.nama}
                  >
                    {nameBusy ? 'Menyimpan…' : 'Simpan Nama'}
                  </button>
                </div>
              </label>
              {nameSuccess && <p className="notice success">{nameSuccess}</p>}
              {nameError && <p className="notice error">{nameError}</p>}
            </form>
          </section>

          {/* Section 2: Fitur Ubah Kata Sandi */}
          <section className="profile-card-section">
            <div className="profile-section-header">
              <h3 className="profile-section-title">Keamanan & Ubah Kata Sandi</h3>
              {otpSent && simulatedEmail && (
                <button
                  type="button"
                  className="text-link"
                  onClick={() => setSimulatedEmail((curr) => curr)}
                >
                  Buka email OTP ✉️
                </button>
              )}
            </div>
            <p className="muted">
              Ubah kata sandi memerlukan kode verifikasi OTP yang disimulasikan melalui pesan email masuk.
            </p>

            {passwordSuccess && <p className="notice success">{passwordSuccess}</p>}
            {passwordError && <p className="notice error">{passwordError}</p>}

            {!otpSent ? (
              <div className="password-trigger-box">
                <p>Klik tombol di bawah untuk menerima kode OTP verifikasi ke email Anda.</p>
                <button
                  type="button"
                  className="button secondary password-action-btn"
                  onClick={handleRequestOtp}
                  disabled={requestBusy}
                >
                  {requestBusy ? 'Mengirim kode OTP…' : '✉️ Minta Kode OTP & Ubah Sandi'}
                </button>
              </div>
            ) : (
              <form onSubmit={handleChangePassword} className="profile-password-form">
                <div className="otp-alert-box">
                  <span className="otp-alert-icon">✉️</span>
                  <div>
                    <b>Kode OTP telah dikirimkan ke {user.email}!</b>
                    <p>Periksa popup pesan email untuk melihat 6 digit kode OTP verifikasi.</p>
                  </div>
                </div>

                <div className="profile-input-group">
                  <label htmlFor="otp-input">Kode OTP Verifikasi
                    <input
                      id="otp-input"
                      type="text"
                      required
                      maxLength={6}
                      value={otpInput}
                      onChange={(e) => setOtpInput(e.target.value)}
                      placeholder="6 digit kode OTP (misal: 123456)"
                      className="otp-code-input"
                    />
                  </label>
                </div>

                <div className="profile-input-group">
                  <label htmlFor="new-pass-input">Kata sandi baru
                    <input
                      id="new-pass-input"
                      type="password"
                      required
                      minLength={8}
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      placeholder="Minimal 8 karakter"
                    />
                  </label>
                </div>

                <div className="profile-input-group">
                  <label htmlFor="confirm-pass-input">Konfirmasi kata sandi baru
                    <input
                      id="confirm-pass-input"
                      type="password"
                      required
                      minLength={8}
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                      placeholder="Ulangi kata sandi baru"
                    />
                  </label>
                </div>

                <div className="profile-form-buttons">
                  <button
                    type="submit"
                    className="button primary"
                    disabled={passwordBusy}
                  >
                    {passwordBusy ? 'Menyimpan di database…' : '✓ Konfirmasi & Ganti Kata Sandi'}
                  </button>
                  <button
                    type="button"
                    className="button secondary"
                    onClick={() => {
                      setOtpSent(false)
                      setOtpInput('')
                      setNewPassword('')
                      setConfirmPassword('')
                      setPasswordError('')
                    }}
                  >
                    Batal
                  </button>
                </div>
              </form>
            )}
          </section>
        </div>
      </section>

      {/* Simulated Email Message Popup */}
      {simulatedEmail && (
        <div className="email-sim-backdrop" onClick={() => setSimulatedEmail(null)}>
          <div className="email-sim-window" onClick={(e) => e.stopPropagation()}>
            <header className="email-sim-window-header">
              <div className="email-sim-window-dots">
                <span className="dot-red" />
                <span className="dot-yellow" />
                <span className="dot-green" />
              </div>
              <div className="email-sim-window-title">
                ✉️ Kotak Masuk Email — <b>iBI Mail Client</b>
              </div>
              <button
                type="button"
                className="icon-action email-close-btn"
                aria-label="Tutup email"
                onClick={() => setSimulatedEmail(null)}
              >
                ×
              </button>
            </header>

            <div className="email-sim-meta-pane">
              <div className="email-meta-line">
                <span className="meta-label">Dari:</span>
                <b>Sistem Keamanan iBI Library &lt;security@ibi.library.local&gt;</b>
              </div>
              <div className="email-meta-line">
                <span className="meta-label">Kepada:</span>
                <b>{simulatedEmail.email}</b>
              </div>
              <div className="email-meta-line">
                <span className="meta-label">Waktu:</span>
                <time>{simulatedEmail.time}</time>
              </div>
              <div className="email-meta-line">
                <span className="meta-label">Subjek:</span>
                <span className="meta-subject">[iBI Library] Kode OTP Verifikasi Perubahan Kata Sandi</span>
              </div>
            </div>

            <div className="email-sim-content-pane">
              <div className="email-brand-header">
                <span className="brand-mark small">i</span>
                <div>
                  <b>iBI Library</b>
                  <small>Ruang Baca Digital</small>
                </div>
              </div>

              <p className="email-greeting">Halo, <b>{user.nama}</b>,</p>
              <p>
                Kami menerima permintaan untuk melakukan perubahan kata sandi pada akun Anda.
                Gunakan kode verifikasi (OTP) berikut untuk mengonfirmasi perubahan kata sandi:
              </p>

              <div className="email-otp-display-card">
                <span className="otp-eyebrow">KODE VERIFIKASI SEKALI PAKAI (OTP)</span>
                <div className="email-otp-numbers">{simulatedEmail.otp}</div>
                <div className="email-otp-buttons">
                  <button
                    type="button"
                    className="button secondary email-action-btn"
                    onClick={() => handleCopyOtp(simulatedEmail.otp)}
                  >
                    {copiedOtp ? '✓ Tersalin ke Clipboard' : '📋 Salin Kode OTP'}
                  </button>
                  <button
                    type="button"
                    className="button primary email-action-btn"
                    onClick={() => handleUseOtp(simulatedEmail.otp)}
                  >
                    ✍️ Gunakan Kode di Formulir
                  </button>
                </div>
              </div>

              <div className="email-security-notice">
                <p>
                  <b>Perhatian:</b> Kode OTP ini berlaku selama <b>15 menit</b>. Jangan berikan kode ini kepada siapa pun, termasuk staf perpustakaan.
                </p>
                <small>Jika Anda tidak merasa meminta perubahan kata sandi ini, akun Anda tetap aman dan abaikan pesan ini.</small>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
