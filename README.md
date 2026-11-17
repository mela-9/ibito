# iBI Library

Sistem perpustakaan digital dengan REST API Laravel 12 + MySQL dan SPA React/Vite + Tailwind CSS. Folder backend dan frontend dipisahkan agar dapat dikembangkan atau dideploy secara independen.

## Struktur

```text
ibi-library-backend/
  app/Http/Controllers/       API untuk autentikasi, buku, peminjaman, antrean
  app/Models/                 Model Eloquent sesuai skema iBI Library
  app/Services/CirculationService.php
  database/migrations/        Pengguna, koleksi dan metadata penemuan, peminjaman, antrean, baca, notifikasi
  routes/api.php
ibi-library-frontend/
  src/services/api.js         Axios + Bearer token Sanctum
  src/pages/                  Halaman anggota dan halaman pengelolaan pustakawan
  src/App.jsx                 Router SPA dengan portal peran yang terpisah
```

## Jalankan backend

1. Pastikan PHP 8.2+, Composer, MySQL 8+, dan ekstensi `pdo_mysql` tersedia. Buat database MySQL `ibi_library`.
2. Dari `ibi-library-backend`, jalankan:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. Sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`, lalu jalankan:

   ```powershell
   php artisan migrate
   php artisan serve
   ```

4. Di terminal backend kedua jalankan scheduler (wajib untuk auto-return dan pengingat):

   ```powershell
   php artisan schedule:work
   ```

5. Jika tidak memakai data demo, buat akun pustakawan pertama melalui Tinker:

   ```powershell
   php artisan tinker
   ```

   ```php
   App\Models\User::create([
       'nama' => 'Pustakawan',
       'email' => 'pustakawan@ibi.local',
       'kata_sandi' => 'GANTI_DENGAN_KATA_SANDI_KUAT',
       'peran' => 'pustakawan',
       'status_verifikasi' => true,
   ]);
   ```

   Akun anggota baru menunggu verifikasi pustakawan melalui dashboard.

### Isi data demo

Seeder membuat 100 judul buku, 101 akun anggota terverifikasi, akun pustakawan `pustakawan@ibi.local`, riwayat peminjaman/baca, notifikasi, serta antrean demo. Akun `anggota101@demo.ibi.local` menggabungkan skenario anggota dan mencocokkan ringkasan pada dashboard: tiga pinjaman aktif (*Catatan tentang Ekonomi Kreatif*, *Catatan tentang Hidup Berkelanjutan*, dan *Belajar dari Bahasa Nusantara*), enam antrean menunggu, serta sembilan notifikasi belum dibaca. Akun ini juga memiliki riwayat pinjaman selesai, aktivitas baca, dan satu antrean yang dibatalkan (posisi keempat untuk *Catatan tentang Seni Membatik*). Kata sandi awal pustakawan dan anggota demo adalah `12345678910`. Atur `DEMO_USERS_PASSWORD` untuk mengganti kata sandi keduanya (misalnya `$env:DEMO_USERS_PASSWORD = "KataSandiDemoBaru"` di PowerShell). Menjalankan ulang seeder menyegarkan transaksi/notifikasi milik 101 akun demo tersebut; akun pustakawan yang sudah ada diperbarui, akun lain tidak dihapus.

Jalankan dari folder backend:

```powershell
php artisan db:seed
```

Email akun anggota adalah `anggota001@demo.ibi.local` sampai `anggota101@demo.ibi.local`. Gunakan `anggota101@demo.ibi.local` untuk demonstrasi kondisi anggota gabungan. Jika ingin menggunakan kata sandi lain untuk kedua jenis akun demo, atur `DEMO_USERS_PASSWORD` sebelum menjalankan seeder.

Setelah masuk, anggota diarahkan ke portal baca (beranda dengan pengingat mendesak, rak buku, katalog berfilter, antrean, reader, notifikasi). Beranda menyorot target baca yang belum tercapai saat batas periode tinggal satu hari serta pinjaman yang jatuh tempo dalam tiga hari atau sudah terlewat; semua pinjaman ada di Rak buku. Katalog mendukung filter genre, tahun terbit, dan penulis; setiap buku menyimpan keyword pembeda. Linimasa di halaman antrean hanya menawarkan judul yang sedang diantrekan anggota, sementara detail buku menampilkan akun yang sedang menunggu dalam dialog. Pustakawan diarahkan ke portal administrasi yang terpisah (ringkasan sirkulasi, pengelolaan koleksi, dan verifikasi anggota).

## Jalankan frontend

Dari `ibi-library-frontend`:

```powershell
npm install
npm run dev
```

Buka URL Vite yang ditampilkan (default `http://localhost:5173`). Konfigurasi Vite meneruskan `/api` ke `http://127.0.0.1:8000`. Untuk deployment, atur `VITE_API_URL` ke URL API dan `FRONTEND_URL` pada backend ke origin frontend.

## Aturan sirkulasi

- Kuota buku maksimal 30 peminjaman aktif (dapat dikurangi per judul).
- Saat penuh, anggota masuk antrean FIFO berdasarkan waktu masuk; saat ada slot, anggota tertua otomatis menjadi peminjam.
- Masa pinjam 14 hari. Scheduler berjalan tiap 15 menit untuk timeout, mengembalikan pinjaman, mempromosikan antrean, dan mengirim pengingat.
- Target membaca adalah akumulasi 30 menit dalam setiap blok 3 hari pada hari 1–12 pinjaman. Pengingat dikirim setiap hari selama target blok belum tercapai; jika gagal sampai akhir blok, akses ditutup dan slot dipromosikan ke antrean berikutnya. Hari 13–14 bebas target membaca dan menerima pengingat masa pinjam berakhir. Masa pinjam tetap maksimal 14 hari.
- Maksimal 30 akun dapat menunggu di antrean per judul. Anggota yang sudah login dapat melihat linimasa antrean, nama anggota, posisi, serta status antrean; hanya antreannya sendiri yang dapat dibatalkan.
- Notifikasi dicatat di database saat event terjadi. SPA mengambil pembaruan berkala (polling 15–30 detik); untuk push WebSocket diperlukan konfigurasi layanan broadcasting tambahan.

## Endpoint inti

- `POST /api/register`, `POST /api/login`, `POST /api/logout`, `GET /api/me`
- `GET /api/buku?q=...&genre=...&tahun_terbit=...&penulis=...`, `GET /api/buku/{id}`; metadata buku mencakup `tahun_terbit` dan `kata_kunci`
- `GET/POST /api/peminjaman`, `POST /api/peminjaman/{id}/kembali`, `POST /api/aktivitas-baca`
- `GET /api/antrean-saya`, `DELETE /api/antrean/{id}`
- `GET /api/buku/{id}/antrean` (anggota login; linimasa antrean baca-saja)
- `GET /api/notifikasi`, `PATCH /api/notifikasi/{id}/baca`
- Pustakawan: `POST/PUT/DELETE /api/buku`, `GET /api/pustakawan/sirkulasi`, `GET /api/pengguna/belum-verifikasi`, `POST /api/pengguna/{id}/verifikasi`

## Pemeriksaan

```powershell
# backend
php artisan test

# frontend
npm run lint
npm run build
```
