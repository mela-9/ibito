import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { getErrorMessage } from '../services/api'

export default function EBookReader() {
  const { loanId } = useParams()
  const [loan, setLoan] = useState(null)
  const [seconds, setSeconds] = useState(0)
  const [savedMinutes, setSavedMinutes] = useState(0)
  const [acknowledgedMinutes, setAcknowledgedMinutes] = useState(0)
  const [targetMet, setTargetMet] = useState(false)
  const [targetActive, setTargetActive] = useState(true)
  const [daysLeft, setDaysLeft] = useState(null)
  const [error, setError] = useState('')
  const sessionSeconds = useRef(0)
  const pendingMinutes = useRef(0)
  const requestInFlight = useRef(false)

  useEffect(() => {
    api.get('/peminjaman')
      .then(({ data }) => {
        const currentLoan = data.find((item) => String(item.id_peminjaman) === loanId)
        if (!currentLoan) throw new Error('Peminjaman aktif tidak ditemukan.')
        setLoan(currentLoan)
        setSavedMinutes(Number(currentLoan.total_menit_3_hari || 0))
        setTargetMet(Number(currentLoan.total_menit_3_hari || 0) >= 30)
        setTargetActive(Boolean(currentLoan.target_membaca_aktif))
        setDaysLeft(Math.max(0, Math.ceil((new Date(currentLoan.tanggal_jatuh_tempo).getTime() - Date.now()) / 86400000)))
      })
      .catch((requestError) => setError(requestError.response ? getErrorMessage(requestError) : requestError.message))
  }, [loanId])

  useEffect(() => {
    if (!loan) return undefined
    const timer = window.setInterval(() => {
      if (document.visibilityState !== 'visible') return
      sessionSeconds.current += 1
      setSeconds(sessionSeconds.current)
      if (sessionSeconds.current % 60 !== 0) return

      pendingMinutes.current += 1
      if (requestInFlight.current) return

      const duration = Math.min(5, pendingMinutes.current)
      requestInFlight.current = true
      api.post('/aktivitas-baca', { id_peminjaman: Number(loanId), durasi_menit: duration })
        .then(({ data }) => {
          pendingMinutes.current -= duration
          setSavedMinutes(data.total_menit_3_hari)
          setAcknowledgedMinutes((current) => current + duration)
          setTargetMet(data.status_syarat)
          setTargetActive(data.target_membaca_aktif)
          setError('')
        })
        .catch((requestError) => setError(getErrorMessage(requestError)))
        .finally(() => { requestInFlight.current = false })
    }, 1000)
    return () => window.clearInterval(timer)
  }, [loan, loanId])

  useEffect(() => {
    if (!loan) return undefined
    const timer = window.setInterval(() => {
      setDaysLeft(Math.max(0, Math.ceil((new Date(loan.tanggal_jatuh_tempo).getTime() - Date.now()) / 86400000)))
    }, 60000)
    return () => window.clearInterval(timer)
  }, [loan])

  const totalReadMinutes = savedMinutes + Math.max(0, Math.floor(seconds / 60) - acknowledgedMinutes)
  const secondsLabel = `${Math.floor(seconds / 60)}m ${String(seconds % 60).padStart(2, '0')}s`
  const progress = Math.min(100, (totalReadMinutes / 30) * 100)

  if (error && !loan) {
    return <div className="reader-error"><p className="notice error">{error}</p><Link className="button secondary" to="/home">Kembali ke beranda</Link></div>
  }
  if (!loan) return <div className="reader-error muted">Menyiapkan buku…</div>

  return (
    <section className="reader-page">
      <header className="reader-toolbar">
        <Link className="back-link" to="/home">← Keluar reader</Link>
        <div className="reader-book-title"><b>{loan.buku?.judul}</b><span>{loan.buku?.penulis}</span></div>
        <div className="reader-days"><small>SISA PINJAM</small><b>{daysLeft} hari</b></div>
      </header>
      <div className="reader-target">
        <div className="reader-target-title"><div><span className="summary-icon lavender">◷</span><span><b>{targetActive ? 'Target membaca tiap 3 hari' : 'Masa pinjam segera berakhir'}</b><small>{targetActive ? 'Minimal 30 menit dalam setiap periode 3 hari untuk mempertahankan pinjaman' : 'Tidak ada target membaca tambahan pada hari ke-13 dan 14'}</small></span></div>{targetActive && <b>{Math.min(30, totalReadMinutes)} / 30 menit</b>}</div>
        {targetActive && <div className="progress-track"><span style={{ width: `${progress}%` }} /></div>}
        {targetActive && targetMet && <p className="target-success">✓ Syarat membaca 30 menit terpenuhi. Terima kasih sudah membaca!</p>}
      </div>
      {error && <p className="notice error">{error}</p>}
      <div className="reader-workspace">
        <article className="reader-paper">
          {loan.buku?.berkas_ebook ? (
            <iframe title={`Reader ${loan.buku.judul}`} src={loan.buku.berkas_ebook} className="ebook-frame" />
          ) : (
            <>
              <p className="eyebrow centered">iBI DIGITAL READER</p>
              <h1>{loan.buku?.judul}</h1>
              <p className="reader-author">{loan.buku?.penulis}</p>
              <div className="paper-divider"><span>✿</span></div>
              <p>{loan.buku?.ringkasan || 'Konten e-book akan ditampilkan di area ini setelah berkas buku diunggah oleh pustakawan.'}</p>
              <p>Gunakan waktu membaca Anda dengan nyaman. Durasi hanya dihitung saat halaman reader ini terbuka dan terlihat di layar.</p>
              <p className="paper-page">WAKTU MEMBACA SESI INI <b>{secondsLabel}</b></p>
            </>
          )}
        </article>
      </div>
    </section>
  )
}
