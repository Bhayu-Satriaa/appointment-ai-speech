# Klinik Sehat Bersama — Resepsionis Digital Berbasis AI

Aplikasi janji temu klinik dengan **resepsionis digital bernama Rani**. Pasien bisa
membuat janji lewat **chat teks** atau **berbicara langsung** dengan AI. Ada juga
**back office** untuk staf klinik melihat dan mengelola data janji temu.

Dibuat dengan Laravel + MySQL. Percakapan teks memakai endpoint OpenAI-compatible,
sedangkan percakapan suara memakai **Gemini Live API** (speech-to-speech native,
bukan text-to-speech).

---

## Fitur

**Halaman pasien** (`/`)
- Chat teks dengan AI yang bisa cek jadwal, membuat janji, cek, dan membatalkan janji
- Mode suara: bicara langsung dengan AI, lengkap dengan indikator orb, tombol bisukan,
  dan interupsi otomatis (kalau pasien menyela, AI langsung berhenti bicara)
- Janji yang dibuat lewat suara maupun teks langsung tersimpan di database

**Back office** (`/admin`)
- Ringkasan: statistik, beban kerja per dokter, jadwal 7 hari, janji terbaru
- Janji Temu: filter (cari, dokter, status, sumber, rentang tanggal), ubah status, export CSV
- Dokter: daftar dokter beserta beban kerjanya

**Ketahanan**
- Anti double-booking di level database (`UNIQUE` pada dokter + tanggal + jam)
- Model cadangan otomatis kalau penyedia model utama sedang down
- Tema terang/gelap dengan tombol pengalih

---

## Yang Dibutuhkan

| Perangkat | Versi |
|---|---|
| PHP | 8.2 atau lebih baru (ekstensi `pdo_mysql`, `mbstring`, `curl`, `openssl`) |
| Composer | 2.x |
| MySQL | 8.0 atau MariaDB 10.4+ |

Tidak perlu Node.js — CSS dan JavaScript sudah ditulis langsung, tanpa proses build.

Cara paling cepat menyiapkan PHP + MySQL di Windows adalah memasang **Laragon**
(sudah termasuk PHP, MySQL, Composer, dan terminal).

---

## Cara Menjalankan

### 1. Clone

```bash
git clone https://github.com/Bhayu-Satriaa/appointment-ai-speech.git
cd appointment-ai-speech
```

### 2. Pasang dependensi PHP

```bash
composer install
```

### 3. Siapkan file konfigurasi

```bash
cp .env.example .env      # Windows: copy .env.example .env
php artisan key:generate
```

Lalu buka `.env` dan isi bagian yang bertanda **WAJIB DIISI SENDIRI** (lihat bagian
Kunci API di bawah).

### 4. Buat database

Masuk ke MySQL:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE appointment_poc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

Pastikan nama database di `.env` (`DB_DATABASE`) sama dengan yang baru dibuat.
Kalau MySQL-mu memakai password, isi juga `DB_PASSWORD`.

### 5. Buat tabel dan data dokter

```bash
php artisan migrate --seed
```

Perintah ini membuat tabel `dokters` dan `appointments`, lalu mengisi 3 dokter contoh
beserta jadwal praktiknya.

### 6. Jalankan

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Buka:
- Halaman pasien — http://localhost:8000
- Back office — http://localhost:8000/admin

---

## Kunci API

Ada **dua** kunci, dan keduanya **tidak disertakan** di repo ini. Kamu harus
mengisinya sendiri.

### 1. `GEMINI_API_KEY` — untuk fitur suara

- Ambil gratis di https://aistudio.google.com/apikey
- Buat API key baru, lalu tempel ke `.env`

**Kalau tidak diisi:** aplikasi tetap berjalan penuh untuk chat teks. Halaman pasien
akan menampilkan label "Mode teks", dan tombol **Bicara** memberi tahu bahwa layanan
suara belum siap.

### 2. `LLM_API_KEY` + `LLM_BASE_URL` — untuk otak percakapan teks

Chat teks memanggil endpoint apa pun yang **kompatibel dengan OpenAI**
(`/chat/completions`). Jadi kamu bebas memilih penyedia:

| Penyedia | `LLM_BASE_URL` | Contoh model |
|---|---|---|
| OpenAI | `https://api.openai.com/v1` | `gpt-4o-mini` |
| Groq | `https://api.groq.com/openai/v1` | `llama-3.3-70b-versatile` |
| OpenRouter | `https://openrouter.ai/api/v1` | sesuai katalog mereka |
| Gateway lokal | `http://127.0.0.1:20128/v1` | sesuai gateway-mu |

Isi juga `LLM_MODEL` dan `LLM_MODEL_FALLBACK` sesuai nama model di penyedia yang
kamu pakai.

**Kalau tidak diisi:** chat teks akan menampilkan pesan error saat dikirim. Fitur
suara diproses Gemini dan tidak terpengaruh.

> **Penting tentang model cadangan.** `LLM_MODEL_FALLBACK` dipakai otomatis kalau
> model utama gagal (misalnya penyedia mengembalikan 503). Isi dengan model lain
> yang tersedia di penyedia yang sama.

---

## Catatan Penting

### Suara hanya jalan di `localhost` atau HTTPS

Browser hanya mengizinkan akses mikrofon pada konteks aman. Jadi:

- `http://localhost:8000` → **mikrofon diizinkan** ✓
- `http://192.168.x.x:8000` → **mikrofon diblokir** ✗

Kalau ingin menguji dari ponsel, gunakan terowongan HTTPS seperti Cloudflare Tunnel:

```bash
cloudflared tunnel --url http://localhost:8000
```

Kamu akan mendapat alamat `https://...trycloudflare.com` yang bisa dibuka dari ponsel.

### Batas waktu respons model

`LLM_MAX_TOKENS` sengaja dibatasi 300. Waktu tunggu sebanding dengan jumlah token
yang dihasilkan model — tanpa batas, jawaban bisa menjadi sangat panjang dan lambat.
Naikkan nilainya kalau jawaban terasa terlalu pendek.

### Keamanan

- **Jangan pernah commit `.env`.** Sudah dikecualikan di `.gitignore`.
- Back office belum memiliki halaman login. Jangan diakses publik.

---

## Susunan Berkas Penting

```
app/
  Http/Controllers/
    AdminController.php            back office: ringkasan, janji, dokter, export CSV
    Api/ChatController.php         endpoint chat teks
    Api/LiveController.php         token sesi suara, konfigurasi, dan eksekusi tool
  Services/
    BookingService.php             aturan jadwal, ketersediaan slot, buat/cek/batal janji
    ClinicAgent.php                loop tool-calling dengan LLM
  Models/
    Dokter.php, Appointment.php

database/migrations/               tabel dokters & appointments
database/seeders/DokterSeeder.php  3 dokter contoh

public/
  css/app.css, admin.css, voice.css
  js/chat.js, voice.js, pcm-capture.js, theme.js

resources/views/
  chat.blade.php                   halaman pasien
  layouts/app.blade.php            layout halaman pasien
  layouts/admin.blade.php          layout back office
  admin/{dashboard,janji,dokter}.blade.php
```

---

## Alur Singkat

1. Pasien membuka halaman, lalu menulis atau menekan **Bicara**
2. AI memahami maksudnya dan memanggil tool: `cek_jadwal`, `buat_janji`, `cek_janji`,
   atau `batal_janji`
3. AI **selalu meminta konfirmasi** sebelum membuat janji
4. Janji tersimpan di MySQL, dan langsung terlihat di back office

Ketersediaan slot 3 hari ke depan sudah dihitung backend dan disertakan di prompt,
supaya pertanyaan jadwal tidak memerlukan putaran tambahan ke model.

---

## Lisensi

Proyek pembelajaran (Project Based Learning) — Politeknik Negeri Samarinda.
