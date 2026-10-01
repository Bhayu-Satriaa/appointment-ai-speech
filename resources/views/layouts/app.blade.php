<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d9488">
    <title>@yield('judul', 'Klinik Sehat Bersama')</title>
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
    <link rel="stylesheet" href="/css/voice.css">
</head>
<body>
    <header class="app-header">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">
                {{-- Ikon perisai-kesehatan (SVG, bukan emoji) --}}
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 21s-7-4.35-9-8.5C1.5 9 3.5 5.5 7 5.5c2 0 3.2 1.1 5 3 1.8-1.9 3-3 5-3 3.5 0 5.5 3.5 4 7-2 4.15-9 8.5-9 8.5z"/>
                    <path d="M12 9v6M9 12h6"/>
                </svg>
            </span>
            <div class="brand-text">
                <h1>Klinik Sehat Bersama</h1>
                <p>Rani — Resepsionis Digital</p>
            </div>
        </div>

        <div class="header-meta">
            <span class="date-chip">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>
                </svg>
                @yield('tanggal', '')
            </span>
            <span class="date-chip" title="Status layanan">
                <span class="status-dot {{ ($geminiSiap ?? false) ? '' : 'off' }}" aria-hidden="true"></span>
                {{ ($geminiSiap ?? false) ? 'Layanan suara siap' : 'Mode teks' }}
            </span>

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

    @yield('content')

    @stack('scripts')
    <script src="/js/theme.js"></script>
</body>
</html>
