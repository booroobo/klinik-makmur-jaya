# Klinik Makmur Jaya

## Deskripsi Aplikasi

Klinik Makmur Jaya adalah aplikasi web e-commerce/apotek online untuk penjualan obat, pengelolaan stok, pemrosesan pesanan, verifikasi resep, notifikasi, audit aktivitas, dan laporan penjualan. Project terdiri dari backend Laravel API dan frontend React SPA.

## Fitur Utama

- Autentikasi multi-role: admin, apoteker, kasir, dan pelanggan.
- Registrasi pelanggan dengan validasi data diri dan verifikasi email.
- Session/token timeout otomatis 120 menit.
- Katalog obat publik dengan pencarian, filter kategori, filter resep, sorting harga, autocomplete, dan fuzzy fallback sederhana.
- Halaman detail obat dengan informasi produk dan stok batch aktif.
- Keranjang belanja dengan tambah, hapus, ubah qty, dan validasi stok.
- Checkout pelanggan dengan pilihan pickup/delivery dan metode pembayaran.
- Upload resep untuk obat yang membutuhkan resep.
- Verifikasi resep oleh admin/apoteker.
- Manajemen pesanan oleh admin/kasir, termasuk update status dan pembayaran.
- Inventory obat, kategori, supplier, draft obat, dan batch stok.
- Soft delete/restore untuk obat, batch, dan supplier.
- FIFO stock deduction saat checkout dari batch aktif yang belum expired.
- Import obat dari CSV/txt dan endpoint upload file import. XLSX tersedia sebagai validasi upload, tetapi pemrosesan XLSX masih terbatas pada environment saat ini.
- Laporan penjualan, transaksi, obat terlaris, dan obat mendekati kedaluwarsa.
- Export laporan PDF dan Excel.
- Queue job untuk import obat dan generate laporan besar.
- Audit log untuk aktivitas penting.
- Notifikasi in-app, unread count, mark as read, read all, dan redirect ke detail order jika terkait pesanan.
- Dashboard admin dengan ringkasan dan grafik.

Catatan: payment gateway nyata, WebSocket realtime, load balancer, backup otomatis production, dan tracking kurir belum diimplementasikan pada versi saat ini.

## Role Pengguna

| Role | Akses Utama |
|---|---|
| Admin | Dashboard, pelanggan, inventory, supplier, import obat, order, resep, laporan, audit, notifikasi |
| Apoteker | Inventory, supplier, verifikasi resep, notifikasi |
| Kasir | Manajemen pesanan dan notifikasi |
| Pelanggan | Katalog, cart, checkout, riwayat pesanan, detail pesanan, notifikasi |

## Teknologi yang Digunakan

### Backend

- PHP ^8.3
- Laravel ^13.8
- PostgreSQL
- Laravel Sanctum ^4.3
- DomPDF via `barryvdh/laravel-dompdf ^3.1`
- Laravel Excel package `maatwebsite/excel ^1.1`
- Laravel Queue dengan default `database`
- PHPUnit untuk test backend
- Laravel Pint untuk formatting PHP

### Frontend

- React ^19.2.6
- Vite ^8.0.12
- React Router DOM ^7.17.0
- Axios ^1.17.0
- Tailwind CSS ^4.3.0
- Chart.js ^4.5.1
- react-chartjs-2 ^5.3.1
- ESLint ^10.3.0

## Struktur Project

```text
klinik-makmur-jaya/
├── backend/                  # Laravel API
│   ├── app/                  # Controllers, models, services, jobs, middleware
│   ├── config/               # Konfigurasi Laravel, Sanctum, queue, mail
│   ├── database/             # Migration, factory, seeder
│   ├── resources/views/      # Template email dan PDF
│   ├── routes/api.php        # Route API utama
│   └── tests/                # Test backend
├── frontend/                 # React/Vite SPA
│   ├── public/               # Asset publik frontend
│   ├── src/                  # Pages, components, context, API helper, utils
│   ├── package.json          # Dependency dan script frontend
│   └── vite.config.js        # Konfigurasi Vite
├── Arsitektur_Infrastruktur.pdf
├── Tools_Framework.pdf
├── Migrasi_Pembaruan.pdf
├── Dokumentasi_Pelanggan.pdf
└── README.md
```

## Persyaratan Sistem

- PHP 8.3 atau lebih baru.
- Composer.
- Node.js dan npm.
- PostgreSQL.
- Git.
- Ekstensi PHP umum untuk Laravel, PostgreSQL, file upload, mail, dan DomPDF.

## Instalasi Backend

Masuk ke folder backend:

```bash
cd backend
```

Install dependency:

```bash
composer install
```

Salin environment:

```bash
cp .env.example .env
```

Pada Windows PowerShell, gunakan:

```powershell
Copy-Item .env.example .env
```

Generate app key:

```bash
php artisan key:generate
```

Atur database PostgreSQL di `.env`, lalu jalankan migration dan seeder:

```bash
php artisan migrate --seed
```

Buat storage link untuk file publik seperti gambar obat:

```bash
php artisan storage:link
```

Jalankan server Laravel:

```bash
php artisan serve
```

Default backend berjalan di:

```text
http://localhost:8000
```

## Konfigurasi Database

Contoh konfigurasi PostgreSQL di `backend/.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=klinik_makmur_jaya_db
DB_USERNAME=postgres
DB_PASSWORD=your-database-password
```

Buat database terlebih dahulu di PostgreSQL:

```sql
CREATE DATABASE klinik_makmur_jaya_db;
```

## Konfigurasi Session Timeout

Token API menggunakan Laravel Sanctum. Timeout default di `.env.example` adalah 120 menit:

```env
SANCTUM_EXPIRATION=120
SESSION_LIFETIME=120
```

Jika mengubah nilai timeout, jalankan:

```bash
php artisan config:clear
```

## Konfigurasi Email SMTP

Registrasi pelanggan membutuhkan email verifikasi. Agar email benar-benar terkirim, isi SMTP nyata di `backend/.env`. Jangan gunakan `smtp.example.com` untuk production.

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="Klinik Makmur Jaya"
FRONTEND_URL=http://localhost:5173
EMAIL_VERIFICATION_EXPIRATION=1440
```

Setelah mengubah konfigurasi mail:

```bash
php artisan config:clear
```

Untuk development tanpa SMTP nyata, mailer dapat diarahkan ke log, tetapi itu berarti email tidak benar-benar terkirim ke inbox.

## Instalasi Frontend

Masuk ke folder frontend:

```bash
cd frontend
```

Install dependency:

```bash
npm install
```

Folder frontend saat ini tidak memiliki `.env.example`. Jika ingin mengatur API base URL secara eksplisit, buat file `frontend/.env`:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

Jika `VITE_API_BASE_URL` tidak diisi, frontend memakai default:

```text
http://localhost:8000/api
```

Jalankan dev server:

```bash
npm run dev
```

Default frontend berjalan di:

```text
http://localhost:5173
```

## Menjalankan Queue dan Scheduler

Project memakai queue untuk import obat dan generate laporan besar. Queue default di `backend/.env.example` adalah `database`.

Jalankan queue worker:

```bash
cd backend
php artisan queue:work
```

Scheduler bersifat opsional. Jalankan jika ingin memproses command terjadwal seperti pengecekan alert inventory bila sudah dijadwalkan di aplikasi:

```bash
php artisan schedule:work
```

Untuk production, queue worker sebaiknya dijalankan dengan process manager seperti Supervisor atau systemd. Konfigurasi production tersebut belum tersedia di repository.

## Akun Demo

Seeder membuat akun demo berikut. Semua memakai password:

```text
password
```

| Role | Email | Password | Catatan |
|---|---|---|---|
| Admin | admin@example.com | password | Sudah verified |
| Apoteker | apoteker@example.com | password | Sudah verified |
| Kasir | kasir@example.com | password | Sudah verified |
| Pelanggan | pelanggan@example.com | password | Sudah verified |

Catatan: akun pelanggan hasil seeder sudah memiliki `email_verified_at`, sehingga bisa langsung login. Pelanggan baru dari halaman register tetap wajib verifikasi email sebelum login.

## Menjalankan Test

Backend:

```bash
cd backend
php artisan test
```

Route check backend:

```bash
php artisan route:list --path=api
```

Frontend:

```bash
cd frontend
npm run test
npm run lint
npm run build
```

## Build Production

Frontend:

```bash
cd frontend
npm run build
```

Output build berada di:

```text
frontend/dist
```

Backend production perlu konfigurasi web server, environment production, queue worker, storage, dan database yang sesuai. Konfigurasi Nginx/Apache/load balancer production belum tersedia di repository.

## Alur Penggunaan Singkat

1. Pelanggan register dan menerima email verifikasi.
2. Pelanggan klik link verifikasi email.
3. Pelanggan login.
4. Pelanggan mencari dan memilih obat dari katalog.
5. Pelanggan memasukkan obat ke keranjang dan mengatur qty.
6. Pelanggan checkout dan upload resep jika membeli obat resep.
7. Admin/kasir memproses order dan pembayaran.
8. Apoteker memverifikasi resep.
9. Pelanggan memantau status pesanan dan notifikasi.
10. Admin melihat dashboard, laporan, audit log, dan export PDF/Excel.

## Endpoint API Utama

Route API dapat dicek dengan:

```bash
cd backend
php artisan route:list --path=api
```

Beberapa endpoint utama:

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/register` | Registrasi pelanggan |
| POST | `/api/login` | Login user |
| GET | `/api/verify-email/{token}` | Verifikasi email pelanggan |
| POST | `/api/resend-verification-email` | Kirim ulang email verifikasi |
| GET | `/api/catalog/medicines` | Katalog obat publik |
| GET | `/api/catalog/medicines/autocomplete` | Autocomplete obat |
| GET | `/api/catalog/medicines/{id}` | Detail obat publik |
| GET | `/api/cart` | Detail keranjang pelanggan |
| POST | `/api/cart/items` | Tambah item cart |
| PUT | `/api/cart/items/{cartItem}` | Ubah qty item cart |
| POST | `/api/checkout` | Checkout pelanggan |
| GET | `/api/my-orders` | Daftar pesanan pelanggan |
| GET | `/api/my-orders/{order}` | Detail pesanan pelanggan |
| GET | `/api/notifications` | Daftar notifikasi |
| PATCH | `/api/notifications/{notification}/read` | Tandai notifikasi read |
| PATCH | `/api/notifications/read-all` | Tandai semua notifikasi read |
| GET | `/api/admin/dashboard` | Dashboard admin |
| GET | `/api/admin/reports/sales` | Laporan penjualan |
| GET | `/api/admin/reports/sales/export/pdf` | Export PDF laporan |
| GET | `/api/admin/reports/sales/export/excel` | Export Excel laporan |
| POST | `/api/admin/medicines/import` | Import obat |
| GET | `/api/admin/audit-logs` | Audit log |

Endpoint protected membutuhkan header:

```http
Authorization: Bearer <token>
```

## Troubleshooting Singkat

### CSRF token mismatch saat register/login

- Pastikan request frontend mengarah ke endpoint `/api/register` atau `/api/login`.
- Pastikan frontend memakai `VITE_API_BASE_URL=http://localhost:8000/api` jika backend berjalan di port 8000.
- Restart server Laravel setelah mengubah middleware/config.

### Database connection error

- Pastikan PostgreSQL berjalan.
- Pastikan database `klinik_makmur_jaya_db` sudah dibuat.
- Cek `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.
- Jalankan `php artisan config:clear` setelah mengubah `.env`.

### Storage image tidak tampil

- Jalankan `php artisan storage:link`.
- Pastikan file upload tersimpan di disk yang benar.
- Pastikan web server dapat melayani folder `public/storage`.

### Email verifikasi tidak masuk

- Jangan gunakan `MAIL_HOST=smtp.example.com`.
- Isi SMTP nyata dan pastikan credential valid.
- Cek folder spam.
- Jalankan `php artisan config:clear`.

### Queue job tidak berjalan

- Jalankan `php artisan queue:work`.
- Pastikan migration tabel queue sudah berjalan.
- Cek failed jobs dan log Laravel jika import/laporan tidak selesai.

### API 401 atau session expired

- Login ulang jika token sudah lebih dari 120 menit.
- Pastikan header `Authorization: Bearer <token>` dikirim.
- Frontend akan menghapus `kmj_token` dan `kmj_user` saat session expired.

### API 403

- Pastikan role user sesuai halaman/endpoint.
- Admin, apoteker, kasir, dan pelanggan memiliki akses berbeda.
- Cek apakah user diblokir admin.

### CORS atau API base URL salah

- Pastikan backend berjalan di `http://localhost:8000`.
- Pastikan frontend `.env` berisi `VITE_API_BASE_URL=http://localhost:8000/api` jika perlu.
- Restart Vite setelah mengubah file `.env`.

## Catatan Implementasi

- Fitur email verifikasi membutuhkan SMTP nyata agar sesuai penggunaan production.
- Fitur queue tersedia, tetapi worker production dan monitoring belum dikonfigurasi di repository.
- Fitur XLSX import masih terbatas pada environment saat ini; CSV adalah format yang direkomendasikan.
- Notifikasi tersedia sebagai in-app notification melalui API/polling, bukan WebSocket realtime.
- Payment gateway nyata belum diintegrasikan; sistem saat ini mencatat metode pembayaran dan status order/payment melalui backend.

## Lisensi / Catatan

Project ini dibuat untuk kebutuhan tugas/sertifikasi Klinik Makmur Jaya. Dependency pihak ketiga mengikuti lisensi masing-masing package.
