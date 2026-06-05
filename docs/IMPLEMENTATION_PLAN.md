# Implementation Plan - Klinik Makmur Jaya

Tanggal audit: 5 Juni 2026

Dokumen ini merupakan hasil audit source code, bukan spesifikasi final. Source code tetap menjadi sumber kebenaran utama pada setiap fase implementasi.

## 1. Ringkasan Kondisi Proyek

Proyek dipisahkan menjadi React SPA pada `frontend/` dan REST API Laravel pada `backend/`.

- Frontend: React 19, Vite 8, React Router DOM 7, Axios, Tailwind CSS 4, Chart.js.
- Backend: Laravel 13, PHP 8.3+, Sanctum 4, PostgreSQL, DomPDF, Laravel Excel.
- Autentikasi: Sanctum personal access token melalui header Bearer.
- Role: `admin`, `apoteker`, `kasir`, dan `pelanggan`.
- API terdaftar: 45 route pada `php artisan route:list --path=api -v`.
- Authorization saat ini: middleware role pada route dan pemeriksaan ownership manual untuk cart, draft obat, dan order pelanggan.
- Database utama: user, kategori, supplier, obat, batch, draft obat, cart, order, item order, dan resep.

### Baseline verifikasi

- `php artisan route:list --path=api -v`: berhasil, 45 route ditemukan.
- `npm.cmd run lint`: berhasil.
- `npm.cmd run build`: berhasil.
- `php artisan test`: belum dapat dijalankan karena ekstensi PHP `mbstring` tidak aktif.
- Test backend yang tersedia hanya test contoh Laravel dan belum menguji proses bisnis.

### Fitur yang benar-benar terhubung

1. Autentikasi dan role dasar
   - Register pelanggan, login, current user, logout, token persistence, protected route, dan role route.
   - Seeder menyediakan akun untuk empat role.

2. Katalog publik
   - Daftar dan detail obat aktif.
   - Search, filter kategori, filter resep, sorting harga, dan pagination.

3. Keranjang pelanggan
   - Lihat, tambah, ubah jumlah, hapus item, kosongkan, dan validasi jumlah terhadap stok terhitung.

4. Inventory
   - CRUD obat, upload gambar, kategori, batch, supplier API, soft delete/restore obat dan batch.
   - Halaman inventory dipakai admin dan apoteker; tombol mutasi utama disembunyikan untuk apoteker dan backend tetap membatasi mutasi ke admin.

5. Draft obat
   - Simpan, buka, ubah, hapus, upload gambar, ownership, dan masa berlaku tujuh hari.
   - Command pembersihan draft kedaluwarsa tersedia.

6. Checkout dan order pelanggan dalam bentuk dasar
   - Membuat order dan snapshot item dari cart.
   - Mendukung pickup/delivery, metode pembayaran, biaya layanan, upload resep, riwayat order, dan detail order milik pelanggan.

## 2. Gap Fitur Berdasarkan Source Code

### A. Integritas inventory dan checkout

Status: prioritas kritis.

- Checkout hanya memvalidasi stok, tetapi tidak mengurangi quantity pada batch.
- Tidak ada row locking atau strategi concurrency. Dua checkout bersamaan dapat lolos dengan stok yang sama.
- `Medicine::total_stock` menjumlahkan seluruh batch non-deleted tanpa mengecualikan batch kedaluwarsa.
- Catalog, cart, inventory, dan checkout dapat menampilkan atau memakai nilai stok yang berbeda tergantung relasi batch yang dimuat.
- Belum ada pencatatan batch mana yang dipakai oleh suatu transaksi.
- Cart memiliki asumsi satu cart per user, tetapi kolom `carts.user_id` belum unique.
- Nomor batch dan nama kategori/supplier tidak mempunyai constraint uniqueness yang jelas.

Rencana:

- Definisikan stok layak jual: batch tidak terhapus, belum kedaluwarsa, dan quantity lebih dari nol.
- Terapkan pengurangan stok FEFO, yaitu batch dengan tanggal kedaluwarsa terdekat dipakai lebih dahulu.
- Lakukan validasi dan pengurangan stok di dalam satu database transaction dengan `lockForUpdate()`.
- Tambahkan tabel alokasi/mutasi stok bila studi kasus mensyaratkan traceability batch. Rekomendasi: `stock_movements` dengan medicine batch, tipe, quantity, reference, actor, dan timestamp.
- Tambahkan constraint yang aman melalui migration baru, bukan mengubah migration lama yang mungkin sudah pernah dijalankan.
- Tambahkan test checkout sukses, stok kurang, batch kedaluwarsa, rollback, dan concurrent allocation sejauh dapat diuji.

### B. Workflow verifikasi resep

Status: schema tersedia, workflow belum ada.

- Tabel dan model `prescriptions` sudah mempunyai status, apoteker, catatan, dan waktu review.
- Endpoint `/api/apoteker/prescriptions` hanya mengembalikan pesan uji akses.
- UI verifikasi resep berisi data hard-coded dan gambar eksternal.
- Belum ada daftar antrean, detail, approve, reject, validasi transisi status, atau sinkronisasi status order.
- File resep disimpan pada disk `public`. Ini berisiko karena resep merupakan data sensitif dan URL dapat diakses langsung.

Rencana:

- Pindahkan file resep ke storage private dan sediakan endpoint download/preview terotorisasi.
- Tambahkan endpoint list/detail/review resep untuk admin dan apoteker.
- Tetapkan status terkontrol, minimal `pending`, `approved`, dan `rejected`.
- Saat approved/rejected, update prescription dan order dalam transaction yang sama.
- Simpan reviewer, catatan, dan `reviewed_at`.
- Hubungkan halaman `/admin/prescription` ke API nyata.
- Tambahkan test role, file authorization, ownership, transisi status, dan idempotency review.

### C. Pemrosesan order oleh kasir/admin

Status: order pelanggan ada, operasi staf belum ada.

- Endpoint `/api/kasir/orders` hanya endpoint uji role.
- Halaman order admin/kasir berisi satu order hard-coded.
- Belum ada endpoint daftar semua order, filter, detail staf, konfirmasi pembayaran, siap diambil/dikirim, selesai, atau batal.
- Nilai `orders.status` dan `payment_status` berupa string bebas tanpa enum/constant atau service transisi.
- Metode `e_wallet` langsung dianggap paid saat checkout tanpa payment gateway atau bukti pembayaran.
- Belum ada riwayat perubahan status.

Rencana:

- Definisikan state machine order dan payment dalam constant/enum aplikasi.
- Tambahkan endpoint list/detail order staf dengan filter status, payment, fulfillment, tanggal, dan search.
- Tambahkan action endpoint terpisah untuk perubahan proses bisnis, bukan update mass-assignment umum.
- Bedakan otorisasi kasir, apoteker, dan admin pada setiap transisi.
- Jangan menandai e-wallet paid tanpa konfirmasi nyata; untuk scope sertifikasi dapat digunakan konfirmasi manual yang eksplisit.
- Catat setiap perubahan status pada audit/order history.
- Hubungkan `/admin/orders` ke API dan tampilkan loading, empty, error, pagination, serta feedback action.

### D. Dashboard admin

Status: UI hard-coded dan endpoint placeholder.

- Statistik penjualan, jumlah order, stok rendah, stok kedaluwarsa, grafik, dan catatan operasional bersifat statis.
- `/api/admin/dashboard` hanya mengembalikan pesan akses.

Rencana:

- Buat endpoint agregasi dashboard dengan periode yang eksplisit.
- Data minimal: pendapatan transaksi selesai/paid, jumlah order, resep pending, stok rendah, batch mendekati kedaluwarsa, dan tren penjualan.
- Pastikan definisi metrik sama dengan laporan.
- Hubungkan UI dengan Chart.js yang sudah terpasang.
- Tambahkan test agregasi tanggal, status yang dihitung, dan role admin.

### E. Laporan dan export

Status: UI statis; package tersedia tetapi belum digunakan.

- Tidak ada endpoint laporan.
- Tombol PDF dan Excel belum melakukan aksi.
- Belum ada definisi transaksi yang masuk ke omzet, timezone laporan, dan filter periode.

Rencana:

- Buat endpoint preview laporan penjualan dengan filter tanggal.
- Tambahkan export PDF menggunakan DomPDF dan Excel menggunakan Laravel Excel.
- Gunakan query/service agregasi yang sama untuk preview dan export agar hasil konsisten.
- Tambahkan test filter periode, total, authorization, content type, dan file response.

### F. Audit log

Status: belum ada backend maupun schema.

- Halaman audit memakai array hard-coded.
- Belum ada migration, model, service, atau endpoint audit.

Rencana:

- Tambahkan tabel `audit_logs`: actor, action, module, auditable type/id, before, after, metadata, IP, dan timestamp.
- Catat aksi penting: perubahan inventory/batch, review resep, payment/order transition, dan export laporan.
- Hindari menyimpan password, token, atau isi file sensitif.
- Endpoint audit hanya untuk admin dengan filter dan pagination.

### G. Notifikasi

Status: dummy dan route frontend salah sasaran.

- Navbar dan header memakai data hard-coded.
- Komponen `Notifications.jsx` ada, tetapi route `/admin/notifications` merender `DashboardRedirect`, bukan komponen tersebut.
- Tidak ada schema/model/API notifikasi atau status read.

Rencana:

- Implementasikan setelah workflow order/resep stabil karena notifikasi bergantung pada event domain tersebut.
- Gunakan Laravel database notifications atau tabel notifikasi khusus.
- Tambahkan endpoint list, unread count, mark as read, dan mark all as read.
- Tampilkan notifikasi sesuai role.

### H. Supplier dan master data

Status: API supplier tersedia, UI khusus belum ada.

- Supplier dipakai sebagai pilihan pada form inventory.
- File `Categories.jsx`, `Medicines.jsx`, dan `Suppliers.jsx` kosong karena pengelolaan utama digabung di `Inventory.jsx`.
- CRUD supplier belum tersedia melalui UI.
- Validasi uniqueness kategori/supplier belum ada.

Rencana:

- Pertahankan pengelolaan kategori di inventory jika sudah memenuhi studi kasus.
- Tambahkan modal/tab supplier pada inventory atau route khusus hanya bila diminta asesmen.
- Hindari membuat halaman duplikat tanpa kebutuhan.

### I. Registrasi dan profil pelanggan

Status: kontrak UI dan backend tidak sepenuhnya sama.

- Form register mempunyai phone dan address.
- Migration user dan endpoint register hanya menyimpan name, email, password, dan role.
- Tidak ada halaman profil atau endpoint update profil.

Rencana:

- Konfirmasi kebutuhan studi kasus sebelum menambah field.
- Jika dibutuhkan, tambahkan migration kolom pelanggan dan endpoint profile yang terotorisasi.
- Jangan menyimpan field dari frontend tanpa validation backend.

### J. Contact Us

Status: UI lokal saja.

- Submit hanya menampilkan pesan sukses lokal.
- Tidak ada migration, email, endpoint, atau dashboard pesan.

Rencana:

- Prioritas rendah kecuali diwajibkan studi kasus.
- Pilih satu scope: simpan pesan ke database atau kirim email melalui queue.

### K. Automation dan scheduler

Status: command ada, schedule belum ada.

- `medicine-drafts:clear-expired` belum dijadwalkan di `routes/console.php`.
- Queue menggunakan database, tetapi belum ada workflow aplikasi yang memanfaatkannya.

Rencana:

- Jadwalkan cleanup harian setelah test command tersedia.
- Dokumentasikan kebutuhan menjalankan scheduler pada deployment.

### L. Test dan dokumentasi API

Status: belum memadai.

- Hanya ada dua test contoh.
- Dokumentasi hanya mencakup autentikasi dasar.
- Belum ada test authorization matrix, ownership, upload, inventory, cart, checkout, resep, dan order.

Rencana:

- Aktifkan ekstensi PHP `mbstring` terlebih dahulu.
- Gunakan feature test dengan `RefreshDatabase` dan factory/fixture terkontrol.
- Tambahkan dokumentasi request, response, role, error, dan state transition untuk endpoint baru.

## 3. Kebutuhan Studi Kasus Sertifikasi

Dokumen requirement sertifikasi formal tidak ditemukan di repository. Prioritas berikut merupakan inferensi dari UI, package, schema, dan pola aplikasi apotek:

- Login dan hak akses multi-role.
- CRUD master data obat, kategori, supplier, dan batch.
- Transaksi pelanggan dan pemrosesan order staf.
- Pengendalian stok dan kedaluwarsa.
- Verifikasi resep oleh apoteker.
- Laporan penjualan dan export.
- Audit aktivitas pengguna.

Sebelum fase implementasi besar, cocokkan daftar ini dengan lembar studi kasus/asesmen resmi agar tidak mengembangkan fitur di luar bukti kompetensi yang diminta.

## 4. Prioritas Implementasi Bertahap

### Fase 1 - Baseline dan regression tests

Tujuan: mengunci perilaku yang sudah berjalan sebelum mengubah transaksi.

- Aktifkan `mbstring` pada PHP CLI.
- Tambahkan test auth, role middleware, catalog, inventory authorization, cart ownership, checkout dasar, dan customer order ownership.
- Dokumentasikan status/status payment yang saat ini dipakai.
- Tidak mengubah endpoint existing.

### Fase 2 - Integritas stok dan checkout

Tujuan: memastikan transaksi tidak menjual stok kedaluwarsa atau melebihi persediaan.

- Migration constraint/mutasi stok bila dipilih.
- Query stok layak jual yang konsisten.
- FEFO, transaction, locking, dan rollback.
- Update response hanya jika diperlukan tanpa mematahkan field existing.
- Test transaksi dan inventory.

### Fase 3 - Verifikasi resep

Tujuan: menyelesaikan workflow pelanggan ke apoteker.

- Private prescription storage.
- API antrean, detail, preview, approve, dan reject.
- Sinkronisasi order.
- UI apoteker/admin dan test authorization.

### Fase 4 - Pemrosesan order kasir/admin

Tujuan: menyelesaikan lifecycle order setelah checkout/resep.

- State machine, list/detail staf, konfirmasi pembayaran, proses fulfillment, selesai/batal.
- UI kasir/admin.
- History/audit dasar untuk transisi.

### Fase 5 - Dashboard dan laporan

Tujuan: menyediakan bukti operasional dan output sertifikasi.

- Endpoint agregasi dashboard.
- Laporan periode, PDF, dan Excel.
- Chart frontend dan export.

### Fase 6 - Audit log dan notifikasi

Tujuan: traceability dan informasi operasional lintas role.

- Audit schema/service/API/UI.
- Database notifications, unread count, dan read state.

### Fase 7 - Penyempurnaan kebutuhan sekunder

- Supplier UI bila dibutuhkan.
- Profile pelanggan dan penyelarasan field register.
- Contact Us backend.
- Scheduler cleanup draft.
- Dokumentasi deployment dan API lengkap.

## 5. Fitur yang Tidak Akan Disentuh Tanpa Kebutuhan

- Mekanisme token Sanctum dan struktur `AuthContext` yang sudah berjalan.
- Route publik katalog dan bentuk response existing.
- `ProtectedRoute` dan `RoleRoute`.
- CRUD inventory yang sudah berjalan, kecuali perubahan terukur untuk konsistensi stok dan test.
- Soft delete/restore obat dan batch.
- Draft obat dan method spoofing multipart `_method=PUT`.
- Ownership check cart, draft, dan order pelanggan.
- Struktur React/Vite/Tailwind dan Axios instance.
- Halaman About Us yang bersifat informasional.

## 6. Risiko Kompatibilitas

1. Perubahan definisi stok dapat mengubah angka yang tampil di catalog, cart, dan inventory.
2. Pengurangan stok saat checkout dapat memengaruhi order lama jika migration/history dirancang tidak kompatibel.
3. Penambahan state machine harus memetakan nilai status lama: `waiting_prescription`, `paid`, dan `pending_payment`.
4. Pemindahan resep dari public ke private storage memerlukan migrasi file existing atau fallback sementara.
5. Constraint unique baru dapat gagal jika database existing sudah memiliki duplikasi.
6. Export dan agregasi tanggal harus konsisten dengan timezone aplikasi; konfigurasi saat ini belum secara eksplisit memakai timezone operasional klinik.
7. PostgreSQL `ILIKE` dipakai pada search. Mengganti database ke MySQL/SQLite akan memerlukan adaptasi query, terutama pada test.
8. Fallback akun demo frontend berjalan ketika request login gagal karena sebab apa pun. Ini dapat menyamarkan outage API dan menghasilkan sesi UI yang tidak dapat memakai endpoint terproteksi.
9. Resep saat ini tersedia melalui public URL dan perlu perlakuan migrasi yang hati-hati.
10. Worktree sudah berisi banyak perubahan/untracked files. Setiap fase harus menjaga perubahan pengguna dan membatasi patch ke scope fase.

## 7. Rencana Migration, API, Frontend, dan Test

| Area | Migration/Model | API | Frontend | Test utama |
|---|---|---|---|---|
| Baseline | Tidak ada | Tidak ada perubahan kontrak | Tidak ada perubahan besar | Auth, role, ownership, CRUD existing |
| Stok | Constraint dan/atau `stock_movements` | Checkout transactional, stok layak jual | Refresh stok/error sold-out | FEFO, expired batch, rollback, race |
| Resep | Penyesuaian storage; history bila perlu | List/detail/review/private file | Antrean dan review nyata | Role, transisi, file access |
| Order staf | Order history/status support | List/detail/action endpoints | Proses order kasir/admin | State transition, payment, role |
| Dashboard | Umumnya tanpa migration | Aggregation endpoint | Cards dan chart API | Nilai agregasi dan periode |
| Laporan | Umumnya tanpa migration | Preview/PDF/Excel | Filter dan download | Total dan response file |
| Audit | `audit_logs` | List/filter | Tabel audit | Recording, redaction, admin-only |
| Notifikasi | Database notifications | List/read/count | Dropdown dan halaman | Recipient role dan read state |

## 8. Urutan Prompt/Fase yang Disarankan

1. Prompt 1: aktifkan baseline test dan buat feature test untuk fitur existing tanpa mengubah kontrak API.
2. Prompt 2: perbaiki definisi stok layak jual dan checkout transactional dengan FEFO.
3. Prompt 3: implementasikan API dan UI verifikasi resep dengan private file access.
4. Prompt 4: implementasikan state machine dan pemrosesan order kasir/admin.
5. Prompt 5: implementasikan dashboard dari data nyata.
6. Prompt 6: implementasikan laporan periode serta export PDF/Excel.
7. Prompt 7: implementasikan audit log pada aksi penting.
8. Prompt 8: implementasikan notifikasi per role.
9. Prompt 9: selesaikan gap sekunder berdasarkan requirement asesmen resmi.
10. Prompt 10: regression audit, dokumentasi API, dan deployment checklist.

Setiap prompt harus meminta AI membaca ulang source code, menjaga endpoint existing, mengimplementasikan satu fase end-to-end, dan melaporkan migration serta test yang dijalankan.

## 9. Perintah Verifikasi Setiap Fase

Jalankan dari root repository sesuai direktori yang disebutkan.

Backend:

```powershell
cd backend
php artisan route:list --path=api -v
php artisan migrate:status
php artisan test
php artisan config:clear
```

Untuk fase yang menambah migration, verifikasi juga pada database test/temporary yang aman:

```powershell
php artisan migrate:fresh --seed --env=testing
php artisan test
```

Jangan menjalankan `migrate:fresh` pada database development yang berisi data penting.

Frontend:

```powershell
cd frontend
npm.cmd run lint
npm.cmd run build
```

Pemeriksaan tambahan per fase:

- Upload/storage: `php artisan storage:link` dan uji akses file sesuai authorization.
- Scheduler: `php artisan schedule:list` dan uji command secara langsung.
- Queue: jalankan worker pada environment test/dev dan pastikan failed job ditangani.
- Export: verifikasi status HTTP, content type, nama file, serta isi PDF/Excel.
- API baru: dokumentasikan method, URI, role, request, response, validation error, dan transisi status.

## 10. Kriteria Selesai Global

- Tidak ada data dummy pada workflow produksi yang dinyatakan selesai.
- Backend melakukan authorization meskipun frontend menyembunyikan kontrol.
- Stok, resep, payment, dan status order konsisten dalam transaction.
- Endpoint existing tetap kompatibel atau perubahan kontraknya terdokumentasi dan disetujui.
- Feature test mencakup happy path, validation, forbidden, ownership, dan rollback.
- `php artisan test`, lint, dan production build lulus.
- Dokumentasi API dan requirement sertifikasi sesuai implementasi aktual.
