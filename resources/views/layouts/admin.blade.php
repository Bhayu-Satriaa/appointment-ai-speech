<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d9488">
    <title>@yield('judul', 'Back Office') — Klinik Sehat Bersama</title>
    <script>
        // Pasang tema sebelum CSS dimuat supaya tidak ada kedipan tema.
        (function () {
            try {
                var t = localStorage.getItem('tema');
                document.documentElement.dataset.theme = (t === 'dark') ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Varela+Round&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <header class="app-header">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-6h6v6"/>
                </svg>
            </span>
            <div class="brand-text">
                <h1>Back Office Klinik</h1>
                <p>Kelola janji temu &amp; data dokter</p>
            </div>
        </div>

        <div class="header-meta">
            <span class="date-chip">@yield('tanggal', '')</span>
            <a class="btn btn--ghost" href="{{ route('chat') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Halaman Pasien
            </a>

            <button type="button" class="btn-tema" id="btnTema" aria-pressed="false" aria-label="Ganti tema">
                <svg class="ikon-tema ikon-bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                </svg>
                <svg class="ikon-tema ikon-matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>
        </div>
    </header>

    <div class="admin-shell">
        <nav class="admin-nav" aria-label="Navigasi back office">
            <span class="admin-nav__label">Menu</span>

            <a class="admin-nav__link" href="{{ route('admin.dashboard') }}"
               @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/>
                    <rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>
                </svg>
                Ringkasan
            </a>

            <a class="admin-nav__link" href="{{ route('admin.janji') }}"
               @if (request()->routeIs('admin.janji')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 11h18M8 3v4M16 3v4"/>
                </svg>
                Janji Temu
            </a>

            <a class="admin-nav__link" href="{{ route('admin.dokter') }}"
               @if (request()->routeIs('admin.dokter')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                Dokter
            </a>

            <div class="admin-nav__foot">
                <p class="note">Data dibaca langsung dari tabel <code>appointments</code> dan <code>dokters</code> di MySQL.</p>
            </div>
        </nav>

        <main class="admin-main">
            @if (session('sukses'))
                <div class="flash" role="status">{{ session('sukses') }}</div>
            @endif

            @yield('konten')
        </main>
    </div>
    <script src="/js/theme.js"></script>
</body>
</html>
