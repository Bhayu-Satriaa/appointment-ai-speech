@extends('layouts.admin')

@section('judul', 'Ringkasan')
@section('tanggal', $hariIni['hari'].', '.$hariIni['tanggal_lengkap'])

@section('konten')
    <div class="admin-head">
        <div>
            <h1>Ringkasan Operasional</h1>
            <p>Kondisi janji temu klinik hari ini dan sepekan ke depan.</p>
        </div>
        <a class="btn btn--primary" href="{{ route('admin.janji.export') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12"/><path d="m7 12 5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            Unduh CSV
        </a>
    </div>

    <section class="stat-grid" aria-label="Statistik utama">
        <div class="stat-card">
            <span class="stat-card__label">Total janji</span>
            <span class="stat-card__value">{{ number_format($statistik['total']) }}</span>
            <span class="stat-card__hint">seluruh riwayat</span>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">Hari ini</span>
            <span class="stat-card__value">{{ number_format($statistik['hari_ini']) }}</span>
            <span class="stat-card__hint">tidak termasuk batal</span>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">7 hari ke depan</span>
            <span class="stat-card__value">{{ number_format($statistik['tujuh_hari']) }}</span>
            <span class="stat-card__hint">jadwal berjalan</span>
        </div>
        <div class="stat-card stat-card--accent">
            <span class="stat-card__label">Selesai</span>
            <span class="stat-card__value">{{ number_format($statistik['selesai']) }}</span>
            <span class="stat-card__hint">sudah dilayani</span>
        </div>
        <div class="stat-card stat-card--warning">
            <span class="stat-card__label">Dibatalkan</span>
            <span class="stat-card__value">{{ number_format($statistik['batal']) }}</span>
            <span class="stat-card__hint">perlu ditindaklanjuti</span>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">Lewat suara</span>
            <span class="stat-card__value">{{ number_format($statistik['lewat_suara']) }}</span>
            <span class="stat-card__hint">dibuat via percakapan suara</span>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Beban Janji per Dokter</h2>
            <span class="badge">{{ $perDokter->count() }} dokter</span>
        </div>
        <div class="panel__body">
            @php $maksDokter = max(1, $perDokter->max('appointments_count')); @endphp
            <div class="bar-list">
                @foreach ($perDokter as $d)
                    <div class="bar-row">
                        <div class="bar-row__top">
                            <span><strong>{{ $d->nama }}</strong> <span class="muted">· {{ $d->spesialisasi }}</span></span>
                            <span>{{ $d->appointments_count }} janji</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ round($d->appointments_count / $maksDokter * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Jadwal 7 Hari ke Depan</h2>
        </div>
        <div class="panel__body">
            @if (empty($harian))
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 11h18"/>
                    </svg>
                    <div>Belum ada janji untuk sepekan ke depan.</div>
                </div>
            @else
                @php $maksHari = max(1, max($harian)); @endphp
                <div class="bar-list">
                    @foreach ($harian as $tgl => $jumlah)
                        @php $c = \Carbon\Carbon::parse($tgl); @endphp
                        <div class="bar-row">
                            <div class="bar-row__top">
                                <span>{{ $c->isoFormat('dddd, D MMM Y') }}</span>
                                <span>{{ $jumlah }} janji</span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: {{ round($jumlah / $maksHari * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Janji Terbaru Dibuat</h2>
            <a class="btn btn--ghost" href="{{ route('admin.janji') }}">Lihat semua</a>
        </div>
        <div class="tabel-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Jadwal</th>
                        <th>Pasien</th>
                        <th>Dokter</th>
                        <th>Status</th>
                        <th>Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($terbaru as $a)
                        <tr>
                            <td><span class="kode">{{ $a->kode_booking }}</span></td>
                            <td class="nowrap">
                                {{ \Carbon\Carbon::parse($a->tanggal)->isoFormat('ddd, D MMM') }}
                                <span class="muted">{{ substr($a->jam, 0, 5) }}</span>
                            </td>
                            <td>{{ $a->nama_pasien }}</td>
                            <td class="muted">{{ $a->dokter?->nama ?? '-' }}</td>
                            <td><span class="pill pill--{{ $a->status }}">{{ $a->status }}</span></td>
                            <td><span class="pill pill--{{ $a->sumber }}">{{ $a->sumber }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Belum ada data janji temu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
