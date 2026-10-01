@extends('layouts.admin')

@section('judul', 'Dokter')
@section('tanggal', $hariIni['hari'].', '.$hariIni['tanggal_lengkap'])

@section('konten')
    <div class="admin-head">
        <div>
            <h1>Data Dokter</h1>
            <p>Jadwal praktik dan jumlah janji temu yang ditangani.</p>
        </div>
    </div>

    @php $maksJanji = max(1, (int) $dokter->max('appointments_count')); @endphp

    @if ($dokter->isEmpty())
        <div class="panel">
            <div class="panel__body">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    <div>Belum ada data dokter.</div>
                </div>
            </div>
        </div>
    @else
        <section class="panel">
            <div class="panel__head">
                <h2 class="panel__title">Daftar Dokter</h2>
                <span class="badge">{{ $dokter->count() }} dokter</span>
            </div>
            <div class="tabel-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Spesialisasi</th>
                            <th>Jam praktik</th>
                            <th>Hari praktik</th>
                            <th>Durasi slot</th>
                            <th>Total janji</th>
                            <th>Janji aktif</th>
                            <th>Beban</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dokter as $d)
                            <tr>
                                <td><strong>{{ $d->nama }}</strong></td>
                                <td><span class="pill pill--terkonfirmasi">{{ $d->spesialisasi }}</span></td>
                                <td class="nowrap">{{ substr($d->jam_mulai, 0, 5) }}–{{ substr($d->jam_selesai, 0, 5) }}</td>
                                <td>
                                    @php
                                        $namaHari = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
                                        $hari = array_map(fn ($h) => $namaHari[(int) $h] ?? $h, $d->hariPraktikList());
                                    @endphp
                                    {{ implode(', ', $hari) }}
                                </td>
                                <td class="nowrap">{{ $d->durasi_slot }} menit</td>
                                <td>{{ $d->appointments_count }}</td>
                                <td>{{ $d->janji_aktif }}</td>
                                <td style="min-width:140px">
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width: {{ round($d->appointments_count / $maksJanji * 100) }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel__body">
                <p class="note">
                    Jumlah janji dihitung dari seluruh baris di tabel <code>appointments</code> yang menunjuk ke dokter terkait.
                    Baris dengan status <code>batal</code> tetap dihitung di kolom <em>Total janji</em>, tetapi tidak di kolom
                    <em>Janji aktif</em>.
                </p>
            </div>
        </section>
    @endif
@endsection
