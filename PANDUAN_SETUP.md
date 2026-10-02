# Panduan Setup — dari Clone sampai Chat & Suara Berjalan

Ikuti berurutan. Jangan lompat ke langkah 7 sebelum langkah 4-6 selesai, karena
kesalahan konfigurasi di situasi itu adalah penyebab paling umum "project tidak berjalan".

---

## Bagian 0 — Periksa perangkat

Buka terminal (Cmd / PowerShell / Git Bash) dan jalankan:

```bash
php -v
composer -V
```

Yang dibutuhkan:

- **PHP 8.2 atau lebih baru**
- **Composer 2.x**
- **MySQL 8.0+** (bisa dari Laragon)

Cara tercepat di Windows: pasang **Laragon** (https://laragon.org). Sudah termasuk
PHP, Composer, MySQL, dan terminal. **Tidak perlu Node.js** — CSS dan JS proyek ini
sudah jadi, tanpa proses build.

Kalau muncul `php: command not found`, berarti PHP belum masuk PATH. Pakai Terminal
bawaan Laragon, atau tambahkan folder PHP ke PATH.

---

## Bagian 1 — Clone dan pasang dependensi

```bash
git clone https://github.com/Bhayu-Satriaa/appointment-ai-speech.git
cd appointment-ai-speech
composer install
```

Kalau `composer install` gagal, cek dulu bahwa `php -v` berjalan.

---

## Bagian 2 — Siapkan file konfigurasi

```bash
cp .env.example .env
php artisan key:generate
```

> Di Windows Cmd, `cp` tidak dikenal — pakai `copy .env.example .env`.

Sekarang file `.env` sudah ada. **Semua pengaturan selanjutnya diedit di file ini.**
Buka dengan Notepad atau VS Code.

---

## Bagian 3 — Buat database

Pastikan MySQL sudah jalan (di Laragon: klik **Start All**).

Masuk ke MySQL:

```bash
mysql -u root
```

Lalu:

```sql
CREATE DATABASE appointment_poc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

> Kalau MySQL-mu pakai password, jalankan `mysql -u root -p` dan isi `DB_PASSWORD`
> di `.env` sesuai password itu.

---

## Bagian 4 — Buat tabel dan data awal

```bash
php artisan migrate --seed
```

Kalau berhasil, akan muncul keterangan pembuatan tabel `dokters`, `appointments`, dan
seeder 3 dokter.

**Kalau di sini gagal**, biasanya karena database belum dibuat atau `DB_USERNAME` /
`DB_PASSWORD` di `.env` salah. Perbaiki dulu sebelum lanjut.

---

## Bagian 5 — Kunci untuk CHAT TEKS (`LLM_API_KEY`)

Ini yang paling sering melewatkan orang. Chat teks butuh penyedia model yang
**kompatibel OpenAI**.

### Pilih satu penyedia

**Pilihan A — Groq (gratis, paling cepat dicoba)**

1. Buka https://console.groq.com/keys
2. Daftar pakai email atau Google
3. Klik **Create API Key**, beri nama bebas, lalu **salin key-nya**
   (key hanya ditampilkan sekali)

**Pilihan B — OpenAI (berbayar)**

1. Buka https://platform.openai.com/api-keys
2. Login, klik **Create new secret key**, salin

**Pilihan C — OpenRouter**

1. Buka https://openrouter.ai/keys
2. Buat key, salin

### Isi di `.env`

Untuk **Groq**:

```
LLM_BASE_URL=https://api.groq.com/openai/v1
LLM_API_KEY=tempel-key-groq-di-sini
LLM_MODEL=llama-3.3-70b-versatile
LLM_MODEL_FALLBACK=llama-3.3-70b-versatile
LLM_MAX_TOKENS=300
```

Untuk **OpenAI**:

```
LLM_BASE_URL=https://api.openai.com/v1
LLM_API_KEY=tempel-key-openai-di-sini
LLM_MODEL=gpt-4o-mini
LLM_MODEL_FALLBACK=gpt-4o-mini
LLM_MAX_TOKENS=300
```

> ⚠️ **Jangan isi `LLM_BASE_URL` dengan `http://127.0.0.1:20128/v1`.**
> Alamat itu hanya berlaku di komputer tempat gateway-nya berjalan. Di komputer lain
> alamat itu kosong, dan chat akan gagal dengan pesan "Tidak bisa menghubungi
> penyedia model".

---

## Bagian 6 — Kunci untuk SUARA (`GEMINI_API_KEY`)

Fitur bicara memakai Gemini Live API. Tanpa key ini, chat teks tetap jalan normal —
hanya tombol **Bicara** yang belum aktif.

1. Buka https://aistudio.google.com/apikey
2. Login pakai akun Google
3. Klik **Create API key**
4. Salin, lalu isi di `.env`:

```
GEMINI_API_KEY=tempel-key-gemini-di-sini
GEMINI_LIVE_MODEL=models/gemini-2.5-flash-native-audio-preview-12-2025
GEMINI_LIVE_VOICE=Kore
```

---

## Bagian 7 — Samakan jam komputer (khusus fitur suara)

Token sesi suara punya masa berlaku. Kalau jam komputermu melenceng jauh dari jam
Google, sesi suara bisa ditolak dengan pesan **"token expired"** meski key-nya benar.

- **Windows:** klik kanan jam di taskbar → *Adjust date and time* →
  aktifkan **Set time automatically** → klik **Sync now**
- **Linux:** `sudo timedatectl set-ntp true`

---

## Bagian 8 — Bersihkan cache konfigurasi

Setiap kali mengubah `.env`, jalankan ini supaya perubahannya terbaca:

```bash
php artisan config:clear
```

---

## Bagian 9 — Jalankan

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Buka di browser:

- Halaman pasien — http://localhost:8000
- Back office — http://localhost:8000/admin

---

## Bagian 10 — Menguji

### Chat teks

Ketik: `halo`

Rani harus menjawab dalam beberapa detik. Kalau muncul pesan error, lihat bagian
Pemecahan Masalah di bawah — pesannya sekarang menyebutkan persis apa yang harus
diperbaiki.

### Fitur suara

1. Klik tombol **Bicara** di sebelah kanan kotak pesan
2. Browser akan meminta izin mikrofon → klik **Izinkan**
3. Setelah orb muncul, ucapkan: *"Saya mau buat janji dengan dokter gigi besok jam 13:00"*
4. Rani akan menjawab dengan suara

**Mikrofon hanya bisa dipakai di `localhost` atau alamat HTTPS.** Kalau kamu membuka
lewat `http://192.168.x.x:8000`, browser akan memblokir mikrofon — ini aturan browser,
bukan bug. Untuk menguji dari HP, pakai terowongan HTTPS:

```bash
cloudflared tunnel --url http://localhost:8000
```

Lalu buka alamat `https://...trycloudflare.com` yang muncul, dari HP.

---

## Pemecahan Masalah

Pesan error di aplikasi sekarang menyebutkan langsung apa yang harus diperbaiki.
Berikut artinya:

**"Tidak bisa menghubungi penyedia model. Periksa LLM_BASE_URL di file .env..."**
→ `LLM_BASE_URL` menunjuk ke alamat yang tidak bisa dijangkau. Jangan pakai
`127.0.0.1` kecuali kamu memang menjalankan server model di komputer itu.
Ganti ke penyedia publik (Bagian 5).

**"Kunci API model ditolak. Periksa LLM_API_KEY dan LLM_BASE_URL di file .env."**
→ Key salah, kosong, atau tidak cocok dengan penyedia di `LLM_BASE_URL`.
Pastikan key diambil dari penyedia yang sama dengan alamatnya.
Jangan lupa `php artisan config:clear` setelah mengubah.

**"Kunci API model sudah kedaluwarsa. Ganti LLM_API_KEY di file .env."**
→ Key sudah tidak berlaku. Buat key baru di dashboard penyedia.

**"Model tidak ditemukan. Periksa LLM_MODEL dan LLM_BASE_URL di file .env."**
→ Nama model salah atau tidak tersedia di penyedia itu. Salin nama model
persis dari dokumentasi penyedia.

**"Kuota penyedia model sedang habis. Coba lagi beberapa saat lagi."**
→ Batas pemakaian tercapai (umum di paket gratis). Tunggu, atau pakai key lain.

**Chat menjawab tapi jawabannya aneh atau tidak memanggil data**
→ Coba `LLM_MODEL` yang lebih kuat. Model kecil dan murah kadang kurang bisa
mengikuti aturan pemanggilan tool.

**"Sesi suara berakhir: token expired"**
→ Jam komputer melenceng. Kerjakan Bagian 7.

**"Mikrofon tidak mengirim suara..."**
→ Mikrofon tidak terdeteksi atau diblokir. Cek izin mikrofon di browser
(ikon gembok di address bar), lalu pastikan perangkat input tidak sedang
di-mute di pengaturan suara Windows.

**"Gemini menolak sesi: ..."**
→ `GEMINI_API_KEY` salah, atau model suara tidak tersedia untuk akunmu.
Pastikan key diambil dari https://aistudio.google.com/apikey.

---

## Urutan cepat untuk yang sudah paham

```bash
git clone https://github.com/Bhayu-Satriaa/appointment-ai-speech.git
cd appointment-ai-speech
composer install
cp .env.example .env
php artisan key:generate
# buat database appointment_poc, lalu isi .env (LLM_* dan GEMINI_API_KEY)
php artisan migrate --seed
php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```
