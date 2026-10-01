@extends('layouts.app')

@section('judul', 'Rani — Resepsionis Digital Klinik Sehat Bersama')
@section('tanggal', $hariIni['hari'].', '.$hariIni['tanggal_lengkap'])

@section('content')
<div class="app-shell">
    <main class="chat-pane">
        <div class="chat-log" id="chatLog" role="log" aria-live="polite" aria-label="Percakapan dengan Rani">
            <div class="msg msg--bot">
                Selamat datang di Klinik Sehat Bersama. Saya Rani, siap membantu Anda membuat janji temu.
                <br><br>
                Anda bisa menulis, atau tekan <strong>Bicara</strong> untuk langsung berbicara dengan saya.
            </div>
        </div>

        <form class="composer" id="formChat" autocomplete="off">
            <label for="inputPesan" class="sr-only">Tulis pesan untuk Rani</label>
            <input id="inputPesan" class="composer__input" type="text"
                   placeholder="Tulis pesan, misalnya: saya mau periksa gigi besok" autocomplete="off">

            <button type="submit" class="btn btn--primary" id="btnKirim">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>
                </svg>
                Kirim
            </button>

            <button type="button" class="btn btn--accent" id="btnSuara" aria-pressed="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="9" y="2" width="6" height="12" rx="3"/>
                    <path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/>
                </svg>
                <span id="labelSuara">Bicara</span>
            </button>
        </form>

        <div class="voice-bar" id="voiceBar" data-open="false" role="status" aria-live="polite">
            <span class="equalizer" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            <span class="voice-bar__label" id="statusSuara">Menyiapkan sesi suara…</span>
        </div>

        {{-- ============ Mode suara: tampilan fokus ============ --}}
        <div class="voice-overlay" id="voiceOverlay" data-aktif="false" data-state="menyiapkan"
             role="dialog" aria-modal="true" aria-label="Mode percakapan suara dengan Rani">

            <div class="voice-card">
                <div class="orb" id="orb" data-state="menyiapkan" aria-hidden="true">
                    <span class="orb__ring orb__ring--luar"></span>
                    <span class="orb__ring"></span>
                    <span class="orb__core"></span>
                </div>

                <div class="voice-status">
                    <span class="voice-status__label">
                        {{-- Ikon berbeda tiap state, supaya tidak bergantung pada warna saja --}}
                        <svg class="voice-status__icon ikon-menyiapkan" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 7v5l3 2"/>
                        </svg>
                        <svg class="voice-status__icon ikon-mendengarkan" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/>
                        </svg>
                        <svg class="voice-status__icon ikon-berpikir" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v3M12 18v3M4.5 7.5l2 2M17.5 14.5l2 2M3 12h3M18 12h3M4.5 16.5l2-2M17.5 9.5l2-2"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="voice-status__icon ikon-berbicara" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 5 6 9H3v6h3l5 4z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M19 6a8 8 0 0 1 0 12"/>
                        </svg>
                        <svg class="voice-status__icon ikon-gagal" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                            <path d="M12 9v4M12 17h.01"/>
                        </svg>
                        <span id="statusTeks">Menyiapkan…</span>
                    </span>
                    <span class="voice-status__detail" id="statusDetail"></span>
                </div>

                <div class="voice-controls">
                    <button type="button" class="btn-glass" id="btnBisu" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 9v3a3 3 0 0 0 4.5 2.6M15 12V5a3 3 0 0 0-5.9-.7"/>
                            <path d="M5 10a7 7 0 0 0 10.7 6M19 10v1M12 17v5M8 22h8"/><path d="m3 3 18 18"/>
                        </svg>
                        <span id="labelBisu">Bisukan</span>
                    </button>

                    <button type="button" class="btn-glass btn-glass--bahaya" id="btnHentikan">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="6" y="6" width="12" height="12" rx="2"/>
                        </svg>
                        Hentikan
                    </button>
                </div>

                <p class="voice-card__hint">
                    Rani menjawab dengan suara. Bicara saja seperti sedang menelepon — tidak perlu menunggu giliran.
                </p>
            </div>

            <details class="transkrip-tutup" id="transkripTutup">
                <summary>Lihat transkrip percakapan</summary>
                <div class="transkrip-isi" id="transkripIsi">
                    <p class="voice-card__hint">Transkrip akan muncul di sini setelah ada percakapan.</p>
                </div>
            </details>
        </div>

        <button type="button" class="btn-turun" id="btnTurun" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 5v14M5 12l7 7 7-7"/>
            </svg>
            Pesan terbaru
        </button>
    </main>
    <aside class="side-panel" aria-label="Informasi klinik">
        <section class="panel-section">
            <div class="panel-section__head">
                <h2 class="panel-section__title">Dokter Praktik</h2>
                <span class="badge">{{ count($daftarDokter) }} dokter</span>
            </div>

            @foreach ($daftarDokter as $d)
                <article class="card">
                    <div class="card__name">{{ $d['nama'] }}</div>
                    <div class="card__specialty">{{ $d['spesialisasi'] }}</div>
                    <div class="card__row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                        </svg>
                        {{ $d['jam_praktik'] }}
                    </div>
                    <div class="card__row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 11h18"/>
                        </svg>
                        {{ $d['hari_praktik'] }}
                    </div>
                </article>
            @endforeach
        </section>

        <section class="panel-section">
            <div class="panel-section__head">
                <h2 class="panel-section__title">Janji Mendatang</h2>
                <span class="badge" id="jumlahJanji">0</span>
            </div>
            <div id="daftarJanji">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 11h18M8 3v4M16 3v4"/>
                    </svg>
                    <div>Belum ada janji temu.<br>Mulai obrolan untuk membuat yang pertama.</div>
                </div>
            </div>
        </section>

        <section class="panel-section">
            <h2 class="panel-section__title" style="margin-bottom:8px">Bukti Data Tersimpan</h2>
            <p class="note">
                Daftar di atas dibaca <strong>langsung dari tabel <code>appointments</code></strong> di MySQL —
                bukan dari ingatan AI. Janji tetap ada setelah halaman di-refresh.
            </p>
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script type="module" src="/js/chat.js"></script>
@endpush
