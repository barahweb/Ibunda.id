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
- Auth lengkap (login, daftar, lupa password). Halaman verifikasi email ada, tapi
  belum diwajibkan: user baru langsung bisa login tanpa verifikasi.
- Role & Permission (`admin` / `participant`) pakai Laravel Policy.
- Service layer (`app/Services/*`) buat pisahin logic bisnis dari komponen Livewire.
- Testing pakai Pest (lihat `tests/`).
- Dashboard analitik buat admin (grafik tren submission, quiz terpopuler, tingkat
  kelulusan).
- Laporan lintas-quiz buat admin (`/admin/reports`) — filter hasil semua peserta dari
  semua quiz sekaligus (nama/email, quiz, status lulus/tidak), bisa export CSV atau
  Excel (.xlsx).
- Kelola User (`/admin/users`) — admin lihat semua user, promote/demote jadi admin,
  gak perlu lagi lewat seeder/tinker manual.
- Security hardening: rate limiting di submit quiz & daftar akun, proteksi race
  condition pas submit, captcha (Cloudflare Turnstile) di halaman daftar.
- Tes Kepribadian (OEJTS) — menu terpisah dari Quiz. 32 pernyataan skala 1-5 (versi
  Indonesia dari Open Extended Jungian Type Scales), hasilnya kode 4 huruf ala MBTI
  (misal INTJ) plus skor per dimensi. Admin kelola pernyataan lewat
  `/admin/assessments` (publish butuh pas 32 pernyataan, 8 per dimensi), peserta lihat
  hasil dan riwayatnya sendiri. Halaman hasil beranimasi (huruf tipe muncul bertahap,
  confetti pas baru selesai) dan bisa diunduh jadi kartu PNG. Peserta juga bisa minta Interpretasi AI (opsional, aktif kalau
  `GEMINI_API_KEY` diisi di file env aplikasi; tier gratis Google Gemini cukup. Bisa
  pindah ke Anthropic dengan `AI_PROVIDER=anthropic` + `ANTHROPIC_API_KEY`): hanya tipe dan skor yang dikirim, tanpa
  nama atau email, dibuat sekali per hasil lalu disimpan, dibatasi 5 permintaan per jam
  per user. Konten OEJTS berlisensi CC BY-NC-SA 4.0 (non-komersial),
  kredit ke [Open Psychometrics](https://openpsychometrics.org/tests/OEJTS/) tampil di
  halaman tes.
- Docker support (opsional, buat VPS, **belum pernah diuji**) — `Dockerfile` +
  `docker-compose.yml`, lihat bagian [Docker](#docker) di bawah.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2 atau lebih baru
- **Frontend:** Livewire 4 (Volt class-based component), Flux UI, Tailwind CSS v4
- **Testing:** Pest v3 (jalan di SQLite in-memory, sudah dicoba juga di MySQL)
- **Database:** MySQL (dev dan production), SQLite buat test

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

> **Status:** opsional dan **belum pernah dijalankan sampai tuntas**. Situs yang live
> sekarang **tidak** memakai Docker, tapi shared hosting cPanel (lihat
> [Opsi B](#opsi-b-shared-hosting-cpanel)). Docker cuma cocok buat VPS atau dicoba di
> komputer sendiri, soalnya shared hosting tidak bisa menjalankan container.

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
   Buat akun admin pertama (akan ditanya nama, email, password):
   ```bash
   docker compose exec app php artisan app:create-admin
   ```
   Cuma buat nyoba lokal, kalau mau data contoh (akun berpassword `password` + quiz +
   tes kepribadian), isi `SEED_DEMO_DATA=true` di `.env.docker`, restart container,
   lalu jalanin `docker compose exec app php artisan db:seed --force`. Jangan lakukan ini
   di server publik.
4. Buka `http://localhost:8080` (atau ganti `APP_PORT` di `.env.docker` kalau port
   8080 udah kepake). Port ini sengaja cuma kebuka di `127.0.0.1` (komputer itu
   sendiri), jadi di server aslinya dipasang reverse proxy HTTPS di depannya, lihat
   [Deploy ke Production](#deploy-ke-production).

Catatan:
- `.env.docker` **terpisah** dari `.env` biasa (buat development lokal/Herd) — isinya
  beda soalnya `DB_HOST` di Docker itu `mysql` (nama service), bukan `127.0.0.1`.
- Setup ini nggabungin nginx + PHP-FPM dalam 1 container lewat `supervisord`, sengaja
  disederhanain (bukan setup production yang di-hardening penuh) biar gampang dicoba.
- **`docker compose up` belum pernah dijalankan** (Docker belum terpasang di komputer
  pengembang). Tiap file (Dockerfile, nginx.conf, entrypoint.sh) baru diperiksa dengan
  dibaca, jadi anggap `docker compose build` pertama sebagai tes. Kalau ada error,
  laporkan lewat issue.

## Deploy ke Production

Panduan ini ngejelasin dua cara naruh aplikasi ini di internet tanpa Laravel Cloud.
Pilih satu.

> **Yang dipakai situs live sekarang: Opsi B (shared hosting cPanel).** Opsi A adalah
> jalur buat VPS dan **belum diuji**.

| | **A. VPS + Docker** (belum diuji) | **B. Shared hosting (cPanel)** (dipakai situs live) |
|---|---|---|
| Biaya kasar | VPS kecil, sekitar $4-6/bulan | Paket hosting biasa, sering lebih murah |
| Cocok kalau | Mau semua fitur jalan penuh | Cuma punya hosting biasa |
| Scheduler & queue | Otomatis (container `scheduler` dan `queue`) | Lewat cron, queue dibuat `sync` |
| Susah-gampang | Perlu sedikit akrab terminal/SSH | Klik-klik di cPanel + upload |

Apa pun pilihannya, baca dulu [Checklist sebelum deploy](#checklist-sebelum-deploy) dan
[Konfigurasi environment production](#konfigurasi-environment-production).

### Checklist sebelum deploy

- [ ] Semua perubahan sudah di-commit dan di-push. File rahasia (`.env`, `.env.docker`)
      **jangan pernah** ikut ke Git (sudah diabaikan lewat `.gitignore`).
- [ ] `php artisan test` hijau di komputermu.
- [ ] Punya **nama domain** (atau subdomain) yang diarahkan ke server. Buat HTTPS dan
      login yang aman, domain itu wajib.
- [ ] Siapkan **password kuat** buat database dan akun admin (jangan pakai `secret` atau
      `password`).
- [ ] (Opsional) key Gemini buat Interpretasi AI, dan key Turnstile buat captcha daftar.
- [ ] (Opsional tapi disarankan) akun email SMTP buat fitur lupa password, misalnya
      Brevo, Mailgun, atau SMTP bawaan hosting.

### Konfigurasi environment production

Nilai di bawah ini diisi di file environment server (`.env.docker` buat Docker, `.env`
buat shared hosting). **Jangan pernah** menyalin file environment lokalmu ke server.

| Variabel | Nilai production | Keterangan |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | `true` bocorin detail error dan data sensitif ke pengunjung |
| `APP_KEY` | hasil `php artisan key:generate --show` | Bikin baru buat server, jangan pakai punya lokal |
| `APP_URL` | `https://domainmu.com` | Pakai `https` |
| `DB_*` | host, nama, user, password database | Password kuat |
| `SESSION_DRIVER` | `database` | |
| `QUEUE_CONNECTION` | `database` (Docker) atau `sync` (shared hosting) | |
| `TRUSTED_PROXIES` | `*` | Cuma kalau ada reverse proxy HTTPS di depan aplikasi (opsi A) |
| `MAIL_MAILER` dan `MAIL_*` | `smtp` + data SMTP-mu | `log` berarti email **tidak terkirim** |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | key asli dari Cloudflare | Jangan pakai test key di production |
| `GEMINI_API_KEY` | key dari aistudio.google.com | Kosong = tombol Interpretasi AI tidak muncul |
| `SEED_DEMO_DATA` | `false` | Lihat peringatan di bawah |

> **Peringatan akun contoh.** Seeder (`php artisan db:seed`) bikin `admin@example.com`
> dan `test@example.com` dengan password `password`. Itu **publik dan gampang ditebak**.
> Di production seeder ini dilewati otomatis. Bikin admin asli lewat
> `php artisan app:create-admin` (ada di langkah di bawah).

Catatan lupa password: email verifikasi daftar belum diwajibkan aplikasi ini (user baru
langsung bisa login), jadi email SMTP terutama dibutuhkan buat **reset password**.

### Opsi A: VPS + Docker

Kita pakai satu VPS Linux (contoh: Ubuntu 24.04), Docker buat jalanin aplikasi, dan
**Caddy** sebagai reverse proxy yang otomatis mengurus sertifikat HTTPS gratis.

> Setup Docker di repo ini **belum pernah dijalankan sampai tuntas**, jadi anggap
> langkah pertama (`docker compose build`) sebagai tes. Kalau ada error, laporkan
> lewat issue.

**1. Siapkan server**

1. Sewa VPS (Hetzner, DigitalOcean, Vultr, atau penyedia lokal), pilih Ubuntu 24.04
   dengan RAM minimal 1 GB (2 GB lebih nyaman buat proses build).
2. Arahkan domain ke VPS: di pengaturan DNS, buat **A record** `domainmu.com` ke IP
   server. Tunggu beberapa menit sampai aktif.
3. Login lewat SSH, lalu amankan dasar-dasarnya:
   ```bash
   ssh root@IP_SERVER
   apt update && apt upgrade -y
   ufw allow OpenSSH
   ufw allow 80
   ufw allow 443
   ufw enable
   ```
   Cuma port SSH, 80, dan 443 yang dibuka. Port database (3306) dan port aplikasi
   (8080) tidak dibuka ke internet.
4. Pasang Docker (cara resmi, lihat [docs.docker.com/engine/install](https://docs.docker.com/engine/install/ubuntu/)):
   ```bash
   curl -fsSL https://get.docker.com | sh
   ```

**2. Ambil kode dan atur environment**

```bash
git clone https://github.com/USERNAME/NAMA-REPO.git /opt/quiz-app
cd /opt/quiz-app
cp docker/env.example .env.docker
nano .env.docker
```

Di `.env.docker` isi minimal:
- `APP_URL=https://domainmu.com`
- Ganti **semua** `secret` (`DB_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`) jadi
  password kuat. Nilai `DB_*` dan `MYSQL_*` harus kembar.
- `MAIL_*`, `TURNSTILE_*`, `GEMINI_API_KEY` kalau dipakai.
- `TRUSTED_PROXIES=*` (sudah bawaan) dan `SEED_DEMO_DATA=false`.

**3. Build, generate key, nyalakan**

```bash
docker compose build
docker compose run --rm app php artisan key:generate --show
```

Tempel hasil `base64:...` ke `APP_KEY=` di `.env.docker`, lalu:

```bash
docker compose up -d
docker compose ps
```

Semua service (`app`, `scheduler`, `queue`, `mysql`) harus berstatus *running*.
Migration jalan otomatis saat `app` start. Cek lognya kalau ragu:
`docker compose logs -f app`.

**4. Pasang Caddy (HTTPS otomatis)**

Ikuti [panduan install Caddy](https://caddyserver.com/docs/install#debian-ubuntu-raspbian),
lalu isi `/etc/caddy/Caddyfile`:

```
domainmu.com {
    reverse_proxy 127.0.0.1:8080
}
```

```bash
systemctl reload caddy
```

Caddy otomatis minta dan memperbarui sertifikat HTTPS, selama DNS domain sudah mengarah
ke server dan port 80/443 terbuka.

**5. Bikin akun admin**

```bash
docker compose exec app php artisan app:create-admin
```

Isi nama, email, dan password (minimal 8 karakter, disarankan jauh lebih panjang). Kalau
emailnya sudah terdaftar sebagai user biasa, user itu dinaikkan jadi admin tanpa mengubah
passwordnya.

**6. Cek hasilnya**

Buka `https://domainmu.com`, lalu:
- [ ] Halaman awal terbuka dengan gembok HTTPS, tanpa peringatan *mixed content*.
- [ ] Login pakai akun admin berhasil.
- [ ] Daftar akun peserta baru berhasil (captcha tampil kalau Turnstile diisi).
- [ ] Admin bisa bikin quiz dan tes kepribadian, peserta bisa mengerjakannya.
- [ ] Tombol Interpretasi AI muncul di hasil tes (kalau `GEMINI_API_KEY` diisi).
- [ ] Reset password mengirim email sungguhan (kalau SMTP diisi).

**Perawatan harian**

| Tugas | Perintah |
|---|---|
| Update ke versi terbaru | `git pull && docker compose build && docker compose up -d` |
| Lihat log | `docker compose logs -f app` |
| Restart (setelah ubah `.env.docker`) | `docker compose up -d --force-recreate` |
| Backup database | `docker compose exec mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup-$(date +%F).sql` |
| Matikan semua (data **aman**) | `docker compose down` |

> **Awas:** `docker compose down -v` ikut **menghapus volume database**, jadi semua data
> hilang. Jangan pakai `-v` kecuali memang mau mulai dari nol. Biasakan backup berkala
> dan simpan filenya di luar server.

### Opsi B: Shared hosting (cPanel)

Syarat hosting: **PHP 8.2 atau lebih baru**, MySQL, akses **SSH** (atau Terminal di
cPanel), dan kemampuan mengubah *document root*. Ekstensi PHP yang dibutuhkan: `pdo_mysql`,
`mbstring`, `bcmath`, `intl`, `zip`, `gd`, `xml`, `fileinfo`. Cek di menu *Select PHP
Version* / *PHP Extensions*.

Karena shared hosting biasanya tidak punya Node.js, **build asset frontend dilakukan di
komputermu**, lalu hasilnya ikut di-upload.

> **Kalau paket hosting tidak punya Terminal/SSH** (seperti situs live ini): buat
> `vendor/` dan `public/build/` di komputer sendiri lalu upload zip-nya lewat File
> Manager. Struktur tabel diimpor lewat phpMyAdmin dari file SQL hasil
> `migrate` di komputer sendiri. Akun admin dibuat dengan daftar lewat aplikasi, lalu
> kolom `role` diubah jadi `admin` lewat phpMyAdmin. `php artisan ...` dan cron
> dilewati (scheduler cuma buat menutup attempt quiz kedaluwarsa, aplikasi tetap
> jalan). Kalau domain utama akun terkunci di `public_html`, taruh isi folder `public`
> di `public_html`, simpan sisanya di folder terpisah (misal `quiz-app`), lalu ubah
> jalur di `public_html/index.php` ke `../quiz-app/...` dan tambahkan
> `$app->usePublicPath(__DIR__);`. Jangan menaruh seluruh proyek di `public_html`.

1. **Build di lokal:**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```
2. **Buat database** di cPanel (*MySQL Databases*): satu database, satu user, hubungkan
   keduanya dengan semua hak akses. Catat nama, user, dan password-nya.
3. **Upload project** (zip lalu extract lewat File Manager, atau `git clone` kalau ada
   SSH) ke folder **di luar** `public_html`, misalnya `/home/USER/quiz-app`. Pastikan
   folder `vendor/` dan `public/build/` ikut terupload.
4. **Arahkan domain ke folder `public`**: di cPanel menu *Domains*, set *Document Root*
   domain ke `quiz-app/public`. Jangan pernah menaruh seluruh project di `public_html`
   karena file environment dan kodenya bisa terunduh orang.
5. **Buat file `.env`** di `quiz-app/` dengan nilai dari tabel
   [konfigurasi production](#konfigurasi-environment-production). Khusus shared hosting:
   `QUEUE_CONNECTION=sync` dan `TRUSTED_PROXIES` dikosongkan. Generate `APP_KEY`-nya di
   komputer lokal (`php artisan key:generate --show`) lalu tempel.
6. **Jalankan lewat SSH/Terminal:**
   ```bash
   cd ~/quiz-app
   php artisan migrate --force
   php artisan app:create-admin
   php artisan config:cache
   php artisan view:cache
   ```
   Kalau `php` di server bukan versi 8.2+, pakai path versinya, misalnya
   `/opt/cpanel/ea-php82/root/usr/bin/php`.
7. **Pasang cron** (cPanel menu *Cron Jobs*, jalan tiap menit) buat scheduler yang
   menutup attempt quiz kedaluwarsa:
   ```
   * * * * * cd /home/USER/quiz-app && php artisan schedule:run >> /dev/null 2>&1
   ```
8. Cek permission: folder `storage/` dan `bootstrap/cache/` harus bisa ditulis
   (`chmod -R 775`). Lalu ikuti [checklist cek hasil](#opsi-a-vps--docker) di atas.

Update di shared hosting: build ulang di lokal, upload perubahan, lalu jalankan
`php artisan migrate --force && php artisan config:cache && php artisan view:cache`.

### Troubleshooting

| Gejala | Kemungkinan penyebab dan solusi |
|---|---|
| Halaman putih / error 500 | Cek `storage/logs/laravel.log` (atau `docker compose logs app`). Sering karena `APP_KEY` kosong atau folder `storage` tidak bisa ditulis. |
| Tampilan berantakan, tanpa CSS | `public/build/` tidak ikut terupload atau `APP_URL` salah. Pada Docker, build asset sudah otomatis di dalam image. |
| Peringatan *mixed content* / link `http://` | Di belakang proxy HTTPS, isi `TRUSTED_PROXIES=*` lalu restart container. |
| Berubah `.env` tapi tidak berpengaruh | Config di-cache. Jalankan `php artisan config:clear` (shared hosting) atau restart container (Docker). |
| Tombol Interpretasi AI tidak muncul | `GEMINI_API_KEY` kosong. Kalau muncul tapi error, lihat log: biasanya model sedang ramai (coba lagi) atau kuota gratis habis. |
| Email reset password tidak sampai | `MAIL_MAILER` masih `log`, atau data SMTP salah. Email `log` hanya tertulis di `storage/logs`. |
| Attempt quiz berbatas waktu tidak ditutup otomatis | Scheduler belum jalan (cron belum dipasang, atau container `scheduler` mati). |
| `Access denied` ke database saat Docker | `DB_*` dan `MYSQL_*` di `.env.docker` tidak sama. Kalau sudah terlanjur dijalankan, password lama tersimpan di volume, jadi hapus volume (`docker compose down -v`, data hilang) atau ubah password lewat MySQL. |

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
- `app/Models/` (Tes Kepribadian) — `Assessment`, `AssessmentQuestion`,
  `AssessmentAttempt`, `AssessmentAnswer`; skoring ada di `app/Helper/OejtsScorer.php`,
  deskripsi 16 tipe di `config/oejts.php`.
- `app/Policies/` — `QuizPolicy`, `QuizAttemptPolicy`, dicek di tiap Volt component
  (`mount()` + tiap action) dan di route middleware (`admin/*` digerbang middleware `admin`).
- `app/Services/` — `QuizService`, `QuestionService`, `QuizAttemptService` (termasuk
  logic scoring & proteksi race condition pas submit), plus `AssessmentService`,
  `AssessmentQuestionService`, `AssessmentAttemptService` buat Tes Kepribadian.
- `resources/views/livewire/` — komponen Volt, dikelompokkan per domain
  (`admin/quizzes/*`, `quizzes/*`, `admin/assessments/*`, `assessments/*`, `auth/*`, `settings/*`).
- `resources/views/livewire/dashboard.blade.php` — dashboard beda tampilan buat admin
  (statistik & grafik) vs peserta (progress pribadi).

## Catatan

Dokumen ini nyatet setup & arsitektur secara ringkas. Detail keputusan desain & histori
pengerjaan tiap modul ada di riwayat commit.
