# Quiz & Assessment Management System

Aplikasi CMS buat bikin, kelola, dan ngerjain quiz/assessment — dibangun pakai Laravel 12,
Livewire (Volt), dan Flux UI. Ada dua peran: **Admin** yang bikin & kelola quiz, dan
**Peserta** yang ngerjain quiz-nya.

## Fitur

**Wajib:**
- Quiz Management — admin bikin, edit, hapus, dan publish/unpublish quiz.
- Question Management — admin kelola soal pilihan ganda per quiz (opsi jawaban, tandai
  yang benar, urutan soal).
- Quiz Submission — peserta ngerjain quiz published, jawaban otomatis diskor dan
  tersimpan tiap kali milih opsi (gak hilang kalau tab ketutup). Kalau quiz punya batas
  waktu, ada countdown live dan jawaban otomatis ke-submit pas waktu habis. Halaman
  riwayat nampilin sisa waktu dan progress soal terjawab buat attempt yang belum selesai.
- Result Display — peserta lihat skor & lulus/tidaknya sendiri (rincian benar/salah
  per soal cuma buat admin, biar quiz-nya tetap adil buat dikerjain ulang).
- Responsive UI — semua halaman jalan di layar mobile sampai desktop.

**Bonus yang udah dikerjain:**
- Auth lengkap (login, daftar, lupa password, verifikasi email).
- Role & Permission (`admin` / `participant`) pakai Laravel Policy.
- Service layer (`app/Services/*`) buat pisahin logic bisnis dari komponen Livewire.
- Testing pakai Pest (lihat `tests/`).
- Dashboard analitik buat admin (grafik tren submission, quiz terpopuler, tingkat
  kelulusan).
- Laporan lintas-quiz buat admin (`/admin/reports`) — filter hasil semua peserta dari
  semua quiz sekaligus (nama/email, quiz, status lulus/tidak), bisa export CSV.
- Kelola User (`/admin/users`) — admin lihat semua user, promote/demote jadi admin,
  gak perlu lagi lewat seeder/tinker manual.
- Security hardening: rate limiting di submit quiz & daftar akun, proteksi race
  condition pas submit, captcha (Cloudflare Turnstile) di halaman daftar.
- Docker support — `Dockerfile` + `docker-compose.yml`, lihat bagian
  [Docker](#docker) di bawah.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.4
- **Frontend:** Livewire 3 (Volt class-based component), Flux UI, Tailwind CSS v4
- **Testing:** Pest v3
- **Database:** SQLite (default), gampang diganti ke MySQL/PostgreSQL

## Instalasi

1. Clone repo ini, lalu masuk ke folder-nya.
2. Install dependency PHP & JS:
   ```bash
   composer install
   npm install
   ```
3. Siapkan file environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Siapkan database (default SQLite):
   ```bash
   touch database/database.sqlite
   ```
   Atau ganti `DB_CONNECTION`, dll di `.env` kalau mau pakai MySQL/PostgreSQL.
5. Jalankan migration + seeder (seeder bikin akun contoh & beberapa quiz):
   ```bash
   php artisan migrate --seed
   ```
6. Build asset frontend:
   ```bash
   npm run build
   ```
7. Jalankan aplikasinya:
   ```bash
   composer run dev
   ```
   Perintah ini jalanin server Laravel, queue listener, log viewer, dan Vite bareng-bareng.
   Kalau pakai [Laravel Herd](https://herd.laravel.com/), aplikasi otomatis bisa diakses
   dari `https://<nama-folder-project>.test` tanpa perlu jalanin `composer run dev`.

### Akun contoh (hasil seeder)

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Peserta | `test@example.com` | `password` |

### Scheduler (penutupan otomatis attempt kadaluarsa)

Attempt quiz yang batas waktunya lewat ditutup otomatis oleh command
`quiz:close-expired-attempts`, dijadwalkan tiap menit lewat scheduler Laravel
(`routes/console.php`). Scheduler harus jalan biar ini aktif:

- **Lokal:** `php artisan schedule:work` di terminal terpisah.
- **Server/production:** pasang cron yang manggil `schedule:run` tiap menit:
  ```
  * * * * * cd /path/ke/project && php artisan schedule:run >> /dev/null 2>&1
  ```
  Atau pakai fitur scheduler bawaan platform hosting kalau ada.

Tanpa scheduler, aplikasi tetap jalan: attempt ditutup pas timer di browser habis atau pas
peserta buka quiz-nya lagi. Scheduler cuma nutup attempt yang ditinggalin begitu aja.

### Captcha di halaman daftar (opsional)

Halaman daftar akun pakai [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/)
buat cegah bot. Butuh 2 env var:

```
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
```

Buat testing lokal tanpa bikin akun Cloudflare, pakai
[test key resmi Cloudflare](https://developers.cloudflare.com/turnstile/troubleshooting/testing/)
yang selalu lolos verifikasi:

```
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

## Docker

Alternatif dari instalasi manual di atas. Satu image dipakai buat 3 peran (`app` yang
serve web, `scheduler`, `queue`), plus container `mysql` terpisah.

1. Salin file environment khusus Docker, lalu isi `APP_KEY`:
   ```bash
   cp docker/env.example .env.docker
   ```
2. Build image-nya dulu, baru generate `APP_KEY` (butuh image-nya buat jalanin artisan):
   ```bash
   docker compose build
   docker compose run --rm app php artisan key:generate --show
   ```
   Tempel hasilnya ke `APP_KEY=` di `.env.docker`.
3. Nyalakan semuanya:
   ```bash
   docker compose up -d
   ```
   Migration jalan otomatis pas container `app` start (lihat `docker/entrypoint.sh`).
   Buat seed data contoh, jalanin manual sekali:
   ```bash
   docker compose exec app php artisan db:seed
   ```
4. Buka `http://localhost:8080` (atau ganti `APP_PORT` di `.env.docker` kalau port
   8080 udah kepake).

Catatan:
- `.env.docker` **terpisah** dari `.env` biasa (buat development lokal/Herd) — isinya
  beda soalnya `DB_HOST` di Docker itu `mysql` (nama service), bukan `127.0.0.1`.
- Setup ini nggabungin nginx + PHP-FPM dalam 1 container lewat `supervisord`, sengaja
  disederhanain (bukan setup production yang di-hardening penuh) biar gampang dicoba.
- **Saya belum bisa nyoba `docker compose up` beneran** di environment kerja saya (gak
  ada Docker terinstall di situ) — udah saya cek manual tiap file-nya (Dockerfile,
  nginx.conf, entrypoint.sh) sebaik mungkin, tapi tolong dicoba sendiri dan kasih tau
  kalau ada yang error.

## Menjalankan Test

```bash
php artisan test
```

Rapiin format kode (Pint) sebelum commit:

```bash
vendor/bin/pint --dirty
```

## Struktur & Arsitektur

Pola yang dipakai di tiap fitur: **Volt component (tipis) → validasi →
Service class (`app/Services/*`) → Model.** Komponen Livewire cuma nanganin
input/otorisasi, logic bisnis (scoring, publish/unpublish, dll) ada di service class.

Bagian penting:
- `app/Models/` — `Quiz`, `Question`, `QuestionOption`, `QuizAttempt`, `QuizAnswer`, `User`
  (kolom `role` buat bedain admin/peserta).
- `app/Policies/` — `QuizPolicy`, `QuizAttemptPolicy`, dicek di tiap Volt component
  (`mount()` + tiap action) dan di route middleware (`admin/*` digerbang middleware `admin`).
- `app/Services/` — `QuizService`, `QuestionService`, `QuizAttemptService` (termasuk
  logic scoring & proteksi race condition pas submit).
- `resources/views/livewire/` — komponen Volt, dikelompokkan per domain
  (`admin/quizzes/*`, `quizzes/*`, `auth/*`, `settings/*`).
- `resources/views/livewire/dashboard.blade.php` — dashboard beda tampilan buat admin
  (statistik & grafik) vs peserta (progress pribadi).

## Catatan

Dokumen ini nyatet setup & arsitektur secara ringkas. Detail keputusan desain & histori
pengerjaan tiap modul ada di riwayat commit.
