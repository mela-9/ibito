import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import api, { getErrorMessage } from '../services/api'

function PageTitle({ eyebrow, title, description, action }) {
  return (
    <div className="page-title-row">
      <div><p className="eyebrow">{eyebrow}</p><h1>{title}</h1>{description && <p className="muted">{description}</p>}</div>
      {action}
    </div>
  )
}

function BookCard({ book }) {
  const active = Number(book.peminjam_aktif || 0)
  const available = Number(book.sisa_kuota ?? Math.max(0, book.kuota_maks - active))
  const keywords = Array.isArray(book.kata_kunci) ? book.kata_kunci : []
  return (
    <article className="book-card">
      <div className="book-cover"><span>{book.kategori || 'KOLEKSI'}</span><b>{book.judul?.slice(0, 1)}</b><small>iBI Library</small></div>
      <div className="book-card-content">
        <p className="book-category">{book.kategori || 'Umum'}</p>
        <h3>{book.judul}</h3>
        <p className="book-author">{book.penulis}{book.tahun_terbit ? ` · ${book.tahun_terbit}` : ''}</p>
        {keywords.length > 0 && <div className="book-keywords">{keywords.slice(0, 3).map((keyword) => <span key={keyword}>{keyword}</span>)}</div>}
        <div className="book-card-footer">
          <span className={`availability ${available > 0 ? 'available' : 'queued'}`}><i />{available > 0 ? `${available} tersedia` : 'Antrean'}</span>
          <Link className="text-link" to={`/books/${book.id_buku}`}>Lihat buku <span>→</span></Link>
        </div>
      </div>
    </article>
  )
}

function getDaysUntil(dateValue) {
  const due = new Date(dateValue)
  const today = new Date()
  due.setHours(0, 0, 0, 0)
  today.setHours(0, 0, 0, 0)
  return Math.floor((due.getTime() - today.getTime()) / 86400000)
}

function getLoanUrgency(loan) {
  const daysUntilDue = Number(loan.hari_sampai_jatuh_tempo ?? getDaysUntil(loan.tanggal_jatuh_tempo))
  const readingMinutes = Number(loan.total_menit_3_hari || 0)
  const readingTargetIsUrgent = loan.target_membaca_aktif
    && readingMinutes < 30
    && Number(loan.hari_sampai_batas_baca ?? Infinity) <= 1

  if (readingTargetIsUrgent && daysUntilDue <= 3) return `Target baca ${readingMinutes}/30 menit dan jatuh tempo ${daysUntilDue < 0 ? 'terlewat' : `dalam ${daysUntilDue} hari`}.`
  if (readingTargetIsUrgent) return `Target baca periode ini baru ${readingMinutes}/30 menit.`
  if (daysUntilDue <= 3) return daysUntilDue < 0 ? 'Masa pinjam sudah melewati tenggat.' : `Jatuh tempo dalam ${daysUntilDue} hari.`
  return ''
}

function LoanCard({ loan, notice, urgent = false }) {
  return (
    <article className={`loan-card ${urgent ? 'loan-card-urgent' : ''}`}>
      <div className="mini-cover">▤</div>
      <div className="loan-card-copy">
        <h3>{loan.buku?.judul}</h3>
        <p className="muted">{loan.buku?.penulis}</p>
        <small>Jatuh tempo {new Date(loan.tanggal_jatuh_tempo).toLocaleDateString('id-ID')}</small>
        {notice && <span className="loan-urgency">{notice}</span>}
      </div>
      <Link className="button secondary" to={`/reader/${loan.id_peminjaman}`}>Baca</Link>
    </article>
  )
}

export function HomePage() {
  const [books, setBooks] = useState([])
  const [loans, setLoans] = useState([])
  const [queues, setQueues] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    Promise.all([api.get('/buku'), api.get('/peminjaman'), api.get('/antrean-saya')])
      .then(([bookResult, loanResult, queueResult]) => {
        setBooks(bookResult.data)
        setLoans(loanResult.data)
        setQueues(queueResult.data)
      })
      .catch((requestError) => setError(getErrorMessage(requestError)))
  }, [])
  const urgentLoans = loans
    .map((loan) => ({ loan, notice: getLoanUrgency(loan) }))
    .filter(({ notice }) => notice)

  return (
    <>
      <section className="welcome-banner">
        <div className="welcome-copy">
          <p className="eyebrow">SENANG BERTEMU LAGI</p>
          <h1>Temukan cerita <em>berikutnya.</em></h1>
          <p>Koleksi bacaan pilihan siap menemani hari Anda. Luangkan waktu sejenak, lalu mulai membaca.</p>
          <Link className="button primary" to="/search">Jelajahi koleksi <span>→</span></Link>
        </div>
        <div className="banner-art" aria-hidden="true"><div className="sun-disc" /><div className="book-stack"><i /><i /><i /></div><span className="sparkle">✦</span><span className="leaf">✿</span></div>
      </section>
      {error && <p className="notice error">{error}</p>}
      <div className="section-heading">
        <div><p className="eyebrow">RINGKASAN ANDA</p><h2>Ruang baca pribadi</h2></div>
      </div>
      <div className="summary-grid">
        <Link className="summary-card" to="/shelf"><span className="summary-icon lavender">▤</span><span><b>Rak buku</b><strong>{loans.length}</strong><small>buku aktif</small></span><i>↗</i></Link>
        <Link className="summary-card" to="/queue"><span className="summary-icon peach">◷</span><span><b>Antrean saya</b><strong>{queues.length}</strong><small>menunggu giliran</small></span><i>↗</i></Link>
      </div>
      <section className="content-section">
        <div className="section-heading"><div><p className="eyebrow">TINDAK LANJUT</p><h2>Perlu perhatian</h2></div>{urgentLoans.length > 0 && <Link className="text-link" to="/shelf">Buka rak buku →</Link>}</div>
        {urgentLoans.length > 0 ? (
          <div className="book-grid">
            {urgentLoans.map(({ loan, notice }) => <LoanCard key={loan.id_peminjaman} loan={loan} notice={notice} urgent />)}
          </div>
        ) : (
          <div className="quiet-state"><span>✓</span><p>Tidak ada pinjaman yang membutuhkan perhatian sekarang.</p><Link className="text-link" to="/shelf">Lihat rak buku →</Link></div>
        )}
      </section>
      <section className="content-section">
        <div className="section-heading"><div><p className="eyebrow">DIPILIH UNTUK ANDA</p><h2>Rekomendasi bacaan</h2></div><Link className="text-link" to="/search">Lihat semua →</Link></div>
        {books.length ? <div className="book-grid">{books.slice(0, 4).map((book) => <BookCard key={book.id_buku} book={book} />)}</div> : <div className="empty-state"><span>✿</span><h3>Rak buku masih kosong</h3><p>Koleksi baru akan muncul di sini.</p></div>}
      </section>
    </>
  )
}

export function BookshelfPage() {
  const [loans, setLoans] = useState([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/peminjaman')
      .then(({ data }) => setLoans(data))
      .catch((requestError) => setError(getErrorMessage(requestError)))
      .finally(() => setLoading(false))
  }, [])

  return (
    <>
      <PageTitle eyebrow="KOLEKSI PRIBADI" title="Rak buku" description="Semua buku yang sedang Anda pinjam, lengkap dengan progres baca dan tenggatnya." />
      {error && <p className="notice error">{error}</p>}
      {loading ? <p className="muted">Memuat rak buku…</p> : loans.length ? (
        <div className="book-grid">
          {loans.map((loan) => (
            <LoanCard
              key={loan.id_peminjaman}
              loan={loan}
              notice={loan.target_membaca_aktif ? `Progres periode ini ${loan.total_menit_3_hari || 0}/30 menit.` : ''}
            />
          ))}
        </div>
      ) : <div className="empty-state"><span>▤</span><h3>Rak buku masih kosong</h3><p>Pinjam buku dari katalog untuk mulai membaca.</p><Link className="button secondary" to="/search">Jelajahi koleksi</Link></div>}
    </>
  )
}

export function SearchPage() {
  const [query, setQuery] = useState('')
  const [books, setBooks] = useState([])
  const [catalog, setCatalog] = useState([])
  const [filters, setFilters] = useState({ genre: '', tahun_terbit: '', penulis: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/buku')
      .then(({ data }) => setCatalog(data))
      .catch((requestError) => setError(getErrorMessage(requestError)))
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setLoading(true)
      const params = {
        ...(query.trim() ? { q: query.trim() } : {}),
        ...(filters.genre ? { genre: filters.genre } : {}),
        ...(filters.tahun_terbit ? { tahun_terbit: filters.tahun_terbit } : {}),
        ...(filters.penulis ? { penulis: filters.penulis } : {}),
      }
      api.get('/buku', { params })
        .then(({ data }) => { setBooks(data); setError('') })
        .catch((requestError) => setError(getErrorMessage(requestError)))
        .finally(() => setLoading(false))
    }, 250)
    return () => window.clearTimeout(timer)
  }, [query, filters])

  const genres = [...new Set(catalog.map((book) => book.kategori).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'id'))
  const years = [...new Set(catalog.map((book) => book.tahun_terbit).filter(Boolean))].sort((a, b) => b - a)
  const authors = [...new Set(catalog.map((book) => book.penulis).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'id'))
  const hasFilters = Boolean(filters.genre || filters.tahun_terbit || filters.penulis)

  function updateFilter(key, value) {
    setFilters((current) => ({ ...current, [key]: value }))
  }

  return (
    <>
      <PageTitle eyebrow="KATALOG iBI" title="Jelajahi koleksi" description="Cari bacaan berdasarkan judul, genre, tahun terbit, atau penulis." />
      <label className="search-field"><span>⌕</span><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Contoh: cerita, sains, atau penulis…" /><kbd>⌘ K</kbd></label>
      <div className="catalog-filters" aria-label="Filter katalog">
        <label>Genre
          <select value={filters.genre} onChange={(event) => updateFilter('genre', event.target.value)}>
            <option value="">Semua genre</option>
            {genres.map((genre) => <option key={genre} value={genre}>{genre}</option>)}
          </select>
        </label>
        <label>Tahun terbit
          <select value={filters.tahun_terbit} onChange={(event) => updateFilter('tahun_terbit', event.target.value)}>
            <option value="">Semua tahun</option>
            {years.map((year) => <option key={year} value={year}>{year}</option>)}
          </select>
        </label>
        <label>Penulis
          <select value={filters.penulis} onChange={(event) => updateFilter('penulis', event.target.value)}>
            <option value="">Semua penulis</option>
            {authors.map((author) => <option key={author} value={author}>{author}</option>)}
          </select>
        </label>
      </div>
      <div className="catalog-meta"><span>{loading ? 'Memuat koleksi…' : `${books.length} buku ditemukan`}</span>{hasFilters && <button className="text-link" onClick={() => setFilters({ genre: '', tahun_terbit: '', penulis: '' })}>Hapus filter</button>}</div>
      {error && <p className="notice error">{error}</p>}
      {books.length > 0 ? <div className="book-grid">{books.map((book) => <BookCard key={book.id_buku} book={book} />)}</div> : !loading && <div className="empty-state"><span>⌕</span><h3>Tidak ada buku ditemukan</h3><p>Coba ubah kata kunci atau filter.</p></div>}
    </>
  )
}

export function BookDetailPage() {
  const { bookId } = useParams()
  const navigate = useNavigate()
  const [book, setBook] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [journeyOpen, setJourneyOpen] = useState(false)
  const [journey, setJourney] = useState([])
  const [journeyLoading, setJourneyLoading] = useState(false)
  const [journeyError, setJourneyError] = useState('')

  useEffect(() => {
    api.get(`/buku/${bookId}`)
      .then(({ data }) => setBook(data))
      .catch((requestError) => setError(getErrorMessage(requestError)))
  }, [bookId])

  async function borrow() {
    setBusy(true)
    setError('')
    try {
      const { data } = await api.post('/peminjaman', { id_buku: Number(bookId) })
      if (data.status === 'borrowed') navigate(`/reader/${data.data.id_peminjaman}`)
      else navigate('/queue')
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  useEffect(() => {
    if (!journeyOpen) return undefined

    function closeOnEscape(event) {
      if (event.key === 'Escape') setJourneyOpen(false)
    }

    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [journeyOpen])

  async function showQueueJourney() {
    setJourneyOpen(true)
    setJourneyLoading(true)
    setJourneyError('')
    try {
      const { data } = await api.get(`/buku/${bookId}/antrean`)
      setJourney(data.filter((entry) => entry.status_antrean === 'menunggu'))
    } catch (requestError) {
      setJourneyError(getErrorMessage(requestError))
    } finally {
      setJourneyLoading(false)
    }
  }

  if (!book) return <div>{error ? <p className="notice error">{error}</p> : <p className="muted">Memuat detail buku…</p>}</div>
  const keywords = Array.isArray(book.kata_kunci) ? book.kata_kunci : []
  return (
    <>
      <Link to="/search" className="back-link">← Kembali ke koleksi</Link>
      {error && <p className="notice error">{error}</p>}
      <section className="detail-layout">
        <div className="detail-cover book-cover"><span>{book.kategori}</span><b>{book.judul.slice(0, 1)}</b><small>iBI Library</small></div>
        <div className="detail-copy">
          <p className="eyebrow">{book.kategori}</p><h1>{book.judul}</h1><p className="book-author">oleh {book.penulis}</p>
          {book.tahun_terbit && <p className="detail-published">Tahun terbit <b>{book.tahun_terbit}</b></p>}
          <div className={`availability ${book.sisa_kuota > 0 ? 'available' : 'queued'}`}><i />{book.sisa_kuota > 0 ? `${book.sisa_kuota} dari ${book.kuota_maks} kuota tersedia` : `${book.antrean_menunggu} orang mengantre`}</div>
          <div className="detail-rule" /><h2>Tentang buku ini</h2>
          <p className="detail-summary">{book.ringkasan || 'Buku ini merupakan bagian dari koleksi digital iBI Library. Pinjam untuk mulai membaca melalui reader digital.'}</p>
          {keywords.length > 0 && <div className="book-keywords detail-keywords">{keywords.map((keyword) => <span key={keyword}>{keyword}</span>)}</div>}
          <div className="detail-facts"><span>Durasi pinjam<b>14 hari</b></span><span>Syarat membaca<b>30 menit / setiap 3 hari (hari 1–12)</b></span></div>
          <button className="button primary" onClick={borrow} disabled={busy}>{busy ? 'Memproses…' : book.sisa_kuota > 0 ? 'Pinjam buku' : 'Gabung antrean'} <span>→</span></button>
          <button className="text-link queue-transparency-link" onClick={showQueueJourney}>Lihat perjalanan antrean buku →</button>
        </div>
      </section>
      {journeyOpen && (
        <div className="dialog-backdrop" onClick={() => setJourneyOpen(false)}>
          <section className="queue-dialog" role="dialog" aria-modal="true" aria-labelledby="queue-dialog-title" onClick={(event) => event.stopPropagation()}>
            <header className="queue-dialog-header">
              <div><p className="eyebrow">LINIMASA ANTREAN</p><h2 id="queue-dialog-title">{book.judul}</h2><p className="muted">Akun yang sedang menunggu giliran untuk buku ini.</p></div>
              <button className="icon-action" aria-label="Tutup" onClick={() => setJourneyOpen(false)}>×</button>
            </header>
            {journeyError && <p className="notice error">{journeyError}</p>}
            {journeyLoading ? <p className="muted">Memuat daftar antrean…</p> : journeyError ? null : journey.length ? (
              <div className="queue-dialog-list">
                {journey.map((entry) => (
                  <article className="queue-dialog-row" key={entry.id_antrean}>
                    <span className="queue-position"><small>POSISI</small><b>{entry.posisi}</b></span>
                    <div><b>{entry.nama_pengguna}</b><small>Masuk antrean {new Date(entry.waktu_masuk).toLocaleString('id-ID')}</small></div>
                  </article>
                ))}
              </div>
            ) : <div className="empty-state queue-dialog-empty"><span>◷</span><h3>Belum ada yang mengantre</h3><p>Daftar akan diperbarui saat ada anggota yang menunggu buku ini.</p></div>}
          </section>
        </div>
      )}
    </>
  )
}

export function AntreanSaya() {
  const [queues, setQueues] = useState([])
  const [journey, setJourney] = useState([])
  const [journeyBookId, setJourneyBookId] = useState('')
  const [error, setError] = useState('')
  const [journeyError, setJourneyError] = useState('')
  const [journeyErrorBookId, setJourneyErrorBookId] = useState('')
  const [loading, setLoading] = useState(true)
  const [searchParams, setSearchParams] = useSearchParams()
  const selectedBookId = searchParams.get('book') || ''
  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get('/antrean-saya')
      setQueues(data)
      setError('')
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    const initialLoad = window.setTimeout(refresh, 0)
    const timer = window.setInterval(refresh, 15000)
    return () => {
      window.clearTimeout(initialLoad)
      window.clearInterval(timer)
    }
  }, [refresh])

  useEffect(() => {
    if (!selectedBookId || loading) {
      return
    }

    const isMyQueue = queues.some((entry) => String(entry.id_buku) === selectedBookId)
    if (!isMyQueue) return

    let active = true
    api.get(`/buku/${selectedBookId}/antrean`)
      .then(({ data }) => {
        if (active) {
          setJourney(data)
          setJourneyBookId(selectedBookId)
          setJourneyError('')
          setJourneyErrorBookId('')
        }
      })
      .catch((requestError) => {
        if (active) {
          setJourneyError(getErrorMessage(requestError))
          setJourneyErrorBookId(selectedBookId)
        }
      })

    return () => { active = false }
  }, [selectedBookId, queues, loading])

  const queueBooks = [...new Map(
    queues.map((entry) => [String(entry.id_buku), entry.buku])
  ).values()]

  function selectBook(event) {
    const bookId = event.target.value
    setSearchParams(bookId ? { book: bookId } : {})
  }

  async function cancel(id) {
    try {
      await api.delete(`/antrean/${id}`)
      setQueues((current) => current.filter((entry) => entry.id_antrean !== id))
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    }
  }

  return (
    <>
      <PageTitle eyebrow="GILIRAN MEMBACA" title="Antrean saya" description="Posisi antrean diperbarui otomatis setiap peralihan akun didalam sistem" />
      {error && <p className="notice error">{error}</p>}
      {loading ? <p className="muted">Memuat antrean…</p> : queues.length === 0 ? <div className="empty-state"><span>◷</span><h3>Belum ada antrean</h3><p>Kuota buku penuh? Bergabunglah untuk mendapatkan giliran berikutnya.</p><Link className="button secondary" to="/search">Cari buku</Link></div> : (
        <div className="queue-list">{queues.map((entry) => (
          <article className="queue-card" key={entry.id_antrean}>
            <div className="queue-position"><small>POSISI</small><b>{entry.posisi}</b></div>
            <div className="queue-book"><div className="mini-cover">▤</div><div><p className="eyebrow">{entry.buku?.kategori}</p><h2>{entry.buku?.judul}</h2><p className="muted">{entry.buku?.penulis}</p><small>Masuk antrean {new Date(entry.waktu_masuk).toLocaleDateString('id-ID')}</small></div></div>
            <div className="queue-estimate"><small>PERKIRAAN TUNGGU</small><b>{entry.estimasi_hari === 0 ? 'Segera' : `~${entry.estimasi_hari} hari`}</b></div>
            <button className="button danger-outline" onClick={() => cancel(entry.id_antrean)}>Keluar antrean</button>
          </article>
        ))}</div>
      )}
      <section className="content-section queue-transparency">
        <div className="section-heading"><div><p className="eyebrow">LINIMASA TERBUKA</p><h2>Perjalanan antrean buku saya</h2><p className="muted">Pilih hanya dari buku yang sedang berada dalam antrean akun Anda.</p></div></div>
        <label className="queue-book-select">Pilih buku
          <select value={selectedBookId} onChange={selectBook}>
            <option value="">Pilih buku di antrean saya…</option>
            {queueBooks.map((book) => <option key={book.id_buku} value={book.id_buku}>{book.judul}</option>)}
          </select>
        </label>
        {journeyError && journeyErrorBookId === selectedBookId && <p className="notice error">{journeyError}</p>}
        {!selectedBookId ? <p className="muted">{queueBooks.length ? 'Pilih salah satu buku di antrean Anda untuk membuka linimasanya.' : 'Tidak ada buku di antrean Anda saat ini.'}</p> : !queues.some((entry) => String(entry.id_buku) === selectedBookId) ? <p className="muted">Linimasa hanya tersedia untuk buku yang sedang Anda antre.</p> : journeyErrorBookId === selectedBookId ? null : journeyBookId !== selectedBookId ? <p className="muted">Memuat perjalanan antrean…</p> : journey.length === 0 ? <p className="muted">Belum ada riwayat antrean untuk buku ini.</p> : (
          <div className="queue-journey-list">
            {journey.map((entry) => (
              <article className="queue-journey-row" key={entry.id_antrean}>
                <span className={`queue-status ${entry.status_antrean}`}>{entry.status_antrean === 'menunggu' ? `Posisi ${entry.posisi}` : entry.status_antrean === 'diproses' ? 'Giliran tiba' : 'Dibatalkan'}</span>
                <b>{entry.nama_pengguna}</b>
                <span>Masuk {new Date(entry.waktu_masuk).toLocaleString('id-ID')}</span>
                <small>{entry.status_antrean === 'menunggu' ? 'Menunggu giliran' : `Status diperbarui ${new Date(entry.updated_at).toLocaleString('id-ID')}`}</small>
              </article>
            ))}
          </div>
        )}
      </section>
      <p className="queue-footnote">Antrean diproses sesuai urutan waktu pendaftaran. Giliran Anda akan otomatis aktif saat kuota tersedia.</p>
    </>
  )
}

export function NotificationsPage({ audience = 'member' }) {
  const [notifications, setNotifications] = useState([])
  const [error, setError] = useState('')
  const isLibrarian = audience === 'librarian'
  const refresh = useCallback(() => api.get('/notifikasi').then(({ data }) => setNotifications(data)).catch((requestError) => setError(getErrorMessage(requestError))), [])
  useEffect(() => {
    refresh()
    const timer = window.setInterval(refresh, 15000)
    return () => window.clearInterval(timer)
  }, [refresh])

  async function markRead(id) {
    try {
      await api.patch(`/notifikasi/${id}/baca`)
      setNotifications((current) => current.map((item) => item.id_notifikasi === id ? { ...item, status_baca: true } : item))
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    }
  }

  return (
    <>
      <PageTitle
        eyebrow={isLibrarian ? 'PUSAT INFORMASI' : 'PEMBARUAN UNTUK ANDA'}
        title={isLibrarian ? 'Notifikasi pustakawan' : 'Notifikasi'}
        description={isLibrarian ? 'Pembaruan aktivitas yang terkait akun dan operasional perpustakaan.' : 'Pembaruan antrean, pengingat membaca, dan masa pinjam.'}
      />
      {error && <p className="notice error">{error}</p>}
      {notifications.length === 0 ? <div className="empty-state"><span>♧</span><h3>{isLibrarian ? 'Belum ada pembaruan' : 'Semua tenang di sini'}</h3><p>Notifikasi baru akan muncul saat ada pembaruan.</p></div> : (
        <div className="notification-list">{notifications.map((item) => (
          <article className={`notification-card ${item.status_baca ? '' : 'unread'}`} key={item.id_notifikasi}>
            <span className="notification-icon">{item.jenis_notifikasi === 'antrean_tiba' ? '◷' : '✿'}</span>
            <div><div className="notification-heading"><b>{item.jenis_notifikasi.replaceAll('_', ' ')}</b><time>{new Date(item.waktu_kirim).toLocaleString('id-ID')}</time></div><p>{item.isi_pesan}</p></div>
            {!item.status_baca && <button className="text-link" onClick={() => markRead(item.id_notifikasi)}>Tandai dibaca</button>}
          </article>
        ))}</div>
      )}
    </>
  )
}

export function DashboardPage() {
  const [stats, setStats] = useState(null)
  const [error, setError] = useState('')
  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get('/pustakawan/sirkulasi')
      setStats(data)
      setError('')
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    }
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(refresh, 0)
    return () => window.clearTimeout(timer)
  }, [refresh])

  return (
    <>
      <PageTitle
        eyebrow="RINGKASAN HARI INI"
        title="Selamat datang, Pustakawan"
        description="Pantau kondisi koleksi dan aktivitas perpustakaan dari satu tempat."
        action={<Link className="button admin-action" to="/librarian/collections">Kelola koleksi <span>→</span></Link>}
      />
      {error && <p className="notice error">{error}</p>}
      {stats ? (
        <div className="admin-stat-grid">
          <article className="admin-stat-card"><span className="admin-stat-icon sage">▤</span><small>Total judul koleksi</small><b>{stats.total_buku}</b><span>tersimpan di katalog</span></article>
          <article className="admin-stat-card"><span className="admin-stat-icon lilac">◷</span><small>Sedang dipinjam</small><b>{stats.peminjaman_aktif}</b><span>akun peminjam aktif</span></article>
          <article className="admin-stat-card"><span className="admin-stat-icon peach">⇄</span><small>Antrean menunggu</small><b>{stats.antrean_menunggu}</b><span>anggota menanti giliran</span></article>
          <article className="admin-stat-card"><span className="admin-stat-icon butter">↗</span><small>Sirkulasi bulan ini</small><b>{stats.peminjaman_selesai_bulan_ini}</b><span>pinjaman selesai / timeout</span></article>
        </div>
      ) : <div className="admin-loading">Memuat ringkasan perpustakaan…</div>}
      <div className="admin-dashboard-grid">
        <section className="admin-panel circulation-panel">
          <div className="admin-panel-heading"><div><p className="eyebrow">AKTIVITAS KOLEKSI</p><h2>Tren sirkulasi</h2></div><span className="admin-period">6 bulan terakhir</span></div>
          <div className="bar-chart admin-chart" aria-label="Grafik sirkulasi peminjaman enam bulan terakhir">
          {(stats?.sirkulasi_bulanan || []).map((month) => {
            const max = Math.max(1, ...stats.sirkulasi_bulanan.map((entry) => entry.total))
            return <div className="bar-column admin-bar-column" key={month.bulan}><b>{month.total}</b><span style={{ height: `${Math.max(8, (month.total / max) * 100)}%` }} /><small>{month.bulan}</small></div>
          })}
          {!stats && <p className="muted">Memuat grafik…</p>}
          </div>
        </section>
        <section className="admin-panel quota-panel">
          <div className="admin-panel-heading"><div><p className="eyebrow">KAPASITAS</p><h2>Ringkasan kuota</h2></div></div>
          {stats?.per_buku?.length ? (
            <div className="admin-inventory">{stats.per_buku.slice(0, 5).map((book) => (
              <div className="admin-inventory-row" key={book.id_buku}>
                <span className="admin-book-dot">{book.judul?.slice(0, 1)}</span>
                <div className="admin-inventory-info"><b>{book.judul}</b><small>{book.peminjam_aktif} dari {book.kuota_maks} peminjam aktif</small><div className="quota-bar"><span style={{ width: `${Math.min(100, (book.peminjam_aktif / book.kuota_maks) * 100)}%` }} /></div></div>
                <span className="admin-queue-count">{book.antrean_menunggu} antre</span>
              </div>
            ))}</div>
          ) : <p className="muted">Belum ada koleksi. Tambahkan buku untuk mulai mengelola sirkulasi.</p>}
          <Link className="admin-panel-link" to="/librarian/collections">Buka pengelolaan koleksi <span>→</span></Link>
        </section>
      </div>
      <section className="admin-tip"><span>✦</span><p><b>Alur anggota dan pustakawan terpisah.</b> Kelola koleksi dan verifikasi pendaftaran melalui menu khusus pustakawan.</p><Link to="/librarian/members">Tinjau anggota <span>→</span></Link></section>
    </>
  )
}

const emptyBookForm = {
  judul: '',
  penulis: '',
  kategori: '',
  tahun_terbit: '',
  kata_kunci: '',
  ringkasan: '',
  berkas_ebook: '',
  kuota_maks: 30,
}

export function LibrarianCollectionsPage() {
  const [books, setBooks] = useState([])
  const [form, setForm] = useState(emptyBookForm)
  const [editingId, setEditingId] = useState(null)
  const [query, setQuery] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)

  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get('/buku')
      setBooks(data)
      setError('')
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    }
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(refresh, 0)
    return () => window.clearTimeout(timer)
  }, [refresh])

  const filteredBooks = books.filter((book) => {
    const text = `${book.judul} ${book.penulis} ${book.kategori}`.toLowerCase()
    return text.includes(query.toLowerCase())
  })

  function editBook(book) {
    setEditingId(book.id_buku)
    setForm({
      judul: book.judul,
      penulis: book.penulis,
      kategori: book.kategori,
      tahun_terbit: book.tahun_terbit || '',
      kata_kunci: Array.isArray(book.kata_kunci) ? book.kata_kunci.join(', ') : '',
      ringkasan: book.ringkasan || '',
      berkas_ebook: book.berkas_ebook || '',
      kuota_maks: book.kuota_maks,
    })
    setMessage('')
    document.getElementById('collection-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  function resetForm() {
    setEditingId(null)
    setForm(emptyBookForm)
  }

  async function saveBook(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setMessage('')
    const payload = {
      ...form,
      tahun_terbit: form.tahun_terbit ? Number(form.tahun_terbit) : null,
      kata_kunci: form.kata_kunci.split(',').map((keyword) => keyword.trim()).filter(Boolean),
      kuota_maks: Number(form.kuota_maks),
    }
    if (payload.kata_kunci.length === 0) {
      setError('Masukkan setidaknya satu keyword pembeda.')
      setBusy(false)
      return
    }
    try {
      if (editingId) {
        await api.put(`/buku/${editingId}`, payload)
        setMessage('Perubahan buku berhasil disimpan.')
      } else {
        await api.post('/buku', payload)
        setMessage('Buku berhasil ditambahkan ke koleksi.')
      }
      resetForm()
      await refresh()
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  async function deleteBook(book) {
    if (!window.confirm(`Hapus "${book.judul}" dari koleksi?`)) return
    setError('')
    setMessage('')
    try {
      await api.delete(`/buku/${book.id_buku}`)
      setMessage('Buku berhasil dihapus.')
      await refresh()
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    }
  }

  return (
    <>
      <PageTitle eyebrow="KATALOG INTERNAL" title="Kelola koleksi" description="Tambah, perbarui, atau hapus buku serta atur kuota peminjam." />
      {error && <p className="notice error">{error}</p>}
      {message && <p className="notice success">{message}</p>}
      <div className="librarian-collection-layout">
        <section className="admin-panel collection-list-panel">
          <div className="admin-panel-heading"><div><p className="eyebrow">DAFTAR BUKU</p><h2>{books.length} judul koleksi</h2></div><span className="admin-count-badge">{books.length}</span></div>
          <label className="search-field admin-search"><span>⌕</span><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari judul, penulis, kategori…" /></label>
          {filteredBooks.length ? (
            <div className="managed-book-list">{filteredBooks.map((book) => (
              <article className="managed-book-card" key={book.id_buku}>
                <div className="managed-book-cover">{book.judul?.slice(0, 1)}</div>
                <div className="managed-book-details"><p className="book-category">{book.kategori}</p><h3>{book.judul}</h3><p className="book-author">{book.penulis}</p><div className="managed-book-meta"><span>{book.peminjam_aktif}/{book.kuota_maks} aktif</span><span>{book.antrean_menunggu} antre</span><span>{book.sisa_kuota} tersedia</span></div></div>
                <div className="managed-book-actions"><button className="icon-action" onClick={() => editBook(book)} aria-label={`Edit ${book.judul}`} title="Edit buku">✎</button><button className="icon-action delete" onClick={() => deleteBook(book)} aria-label={`Hapus ${book.judul}`} title="Hapus buku">×</button></div>
              </article>
            ))}</div>
          ) : <div className="admin-empty"><span>▤</span><b>{query ? 'Buku tidak ditemukan' : 'Belum ada buku'}</b><small>{query ? 'Coba kata kunci lain.' : 'Tambahkan buku baru melalui formulir.'}</small></div>}
        </section>

        <form id="collection-editor" className="admin-panel admin-book-form" onSubmit={saveBook}>
          <div className="admin-panel-heading"><div><p className="eyebrow">{editingId ? 'PERBARUI DATA' : 'KOLEKSI BARU'}</p><h2>{editingId ? 'Edit informasi buku' : 'Tambah buku'}</h2></div><span className="admin-form-icon">{editingId ? '✎' : '+'}</span></div>
          <label>Judul buku<input required maxLength={255} value={form.judul} onChange={(event) => setForm({ ...form, judul: event.target.value })} placeholder="Contoh: Laut Bercerita" /></label>
          <label>Nama penulis<input required maxLength={255} value={form.penulis} onChange={(event) => setForm({ ...form, penulis: event.target.value })} placeholder="Nama penulis" /></label>
          <label>Kategori<input required maxLength={255} value={form.kategori} onChange={(event) => setForm({ ...form, kategori: event.target.value })} placeholder="Fiksi, sejarah, sains…" /></label>
          <label>Tahun terbit<input required type="number" min="1000" max="2100" value={form.tahun_terbit} onChange={(event) => setForm({ ...form, tahun_terbit: event.target.value })} placeholder="Contoh: 2024" /></label>
          <label>Keyword pembeda<input required value={form.kata_kunci} onChange={(event) => setForm({ ...form, kata_kunci: event.target.value })} placeholder="Pesisir, sejarah maritim, pelayaran" /><small>Pisahkan setiap keyword dengan koma.</small></label>
          <label>Sinopsis<textarea rows="3" value={form.ringkasan} onChange={(event) => setForm({ ...form, ringkasan: event.target.value })} placeholder="Ringkasan singkat buku" /></label>
          <label>Lokasi / URL e-book<input value={form.berkas_ebook} onChange={(event) => setForm({ ...form, berkas_ebook: event.target.value })} placeholder="/storage/ebook/buku.pdf" /></label>
          <label>Kuota peminjam aktif<input required type="number" min="1" max="30" value={form.kuota_maks} onChange={(event) => setForm({ ...form, kuota_maks: event.target.value })} /><small>Maksimal 30 akun peminjam per judul.</small></label>
          <div className="admin-form-actions"><button className="button admin-action" disabled={busy}>{busy ? 'Menyimpan…' : editingId ? 'Simpan perubahan' : 'Tambah ke koleksi'}</button>{editingId && <button type="button" className="button admin-cancel" onClick={resetForm}>Batal</button>}</div>
        </form>
      </div>
      <p className="admin-duration-note">Durasi pinjam standar <b>14 hari</b>. Pengingat syarat baca dikirim pada tiga hari pertama.</p>
    </>
  )
}

export function LibrarianMembersPage() {
  const [members, setMembers] = useState([])
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  const refresh = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await api.get('/pengguna/belum-verifikasi')
      setMembers(data)
      setError('')
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(refresh, 0)
    return () => window.clearTimeout(timer)
  }, [refresh])

  async function verifyMember(member) {
    setBusyId(member.id_pengguna)
    setError('')
    setMessage('')
    try {
      await api.post(`/pengguna/${member.id_pengguna}/verifikasi`)
      setMembers((current) => current.filter((item) => item.id_pengguna !== member.id_pengguna))
      setMessage(`Akun ${member.nama} berhasil diverifikasi dan kini dapat masuk.`)
    } catch (requestError) {
      setError(getErrorMessage(requestError))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <>
      <PageTitle eyebrow="AKSES ANGGOTA" title="Verifikasi pendaftaran" description="Tinjau pendaftaran sebelum anggota menggunakan layanan perpustakaan." action={<button className="button admin-cancel" onClick={refresh}>Muat ulang ↻</button>} />
      {error && <p className="notice error">{error}</p>}
      {message && <p className="notice success">{message}</p>}
      <div className="member-review-banner"><span>♙</span><div><b>{members.length} akun menunggu tinjauan</b><p>Anggota dapat masuk setelah pustakawan menyetujui pendaftarannya.</p></div></div>
      {loading ? <div className="admin-loading">Memuat pendaftar…</div> : members.length ? (
        <section className="member-review-list">{members.map((member) => (
          <article className="member-review-card" key={member.id_pengguna}>
            <div className="avatar member-review-avatar">{member.nama?.slice(0, 1).toUpperCase()}</div>
            <div className="member-review-details"><b>{member.nama}</b><span>{member.email}</span><small>Mendaftar {new Date(member.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' })}</small></div>
            <span className="pending-pill"><i />Menunggu</span>
            <button className="button admin-action" disabled={busyId === member.id_pengguna} onClick={() => verifyMember(member)}>{busyId === member.id_pengguna ? 'Memverifikasi…' : '✓ Verifikasi'}</button>
          </article>
        ))}</section>
      ) : <div className="admin-empty member-empty"><span>✓</span><b>Semua pendaftaran sudah ditinjau</b><small>Pendaftar baru akan tampil di halaman ini.</small></div>}
    </>
  )
}
