@extends('layouts.admin')

@section('judul', 'Janji Temu')
@section('tanggal', $hariIni['hari'].', '.$hariIni['tanggal_lengkap'])

@section('konten')
    <div class="admin-head">
        <div>
            <h1>Janji Temu</h1>
            <p>{{ number_format($janji->total()) }} data ditemukan.</p>
        </div>
        <a class="btn btn--primary" href="{{ route('admin.janji.export', request()->query()) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12"/><path d="m7 12 5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            Unduh CSV
        </a>
    </div>

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Filter &amp; Pencarian</h2>
            <a class="btn-mini" href="{{ route('admin.janji') }}">Reset filter</a>
        </div>
        <div class="panel__body">
            <form class="toolbar" method="GET" action="{{ route('admin.janji') }}">
                <div class="field field--grow">
                    <label for="cari">Cari</label>
                    <input id="cari" name="cari" type="search" value="{{ $filter['cari'] }}"
                           placeholder="Nama pasien, kode booking, telepon, atau keluhan">
                </div>

                <div class="field">
                    <label for="dokter_id">Dokter</label>
                    <select id="dokter_id" name="dokter_id">
                        <option value="">Semua dokter</option>
                        @foreach ($daftarDokter as $d)
                            <option value="{{ $d->id }}" @selected((string) $filter['dokter_id'] === (string) $d->id)>
                                {{ $d->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Semua status</option>
                        @foreach ($daftarStatus as $s)
                            <option value="{{ $s }}" @selected($filter['status'] === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="sumber">Sumber</label>
                    <select id="sumber" name="sumber">
                        <option value="">Semua sumber</option>
                        <option value="chat" @selected($filter['sumber'] === 'chat')>chat</option>
                        <option value="suara" @selected($filter['sumber'] === 'suara')>suara</option>
                    </select>
                </div>

                <div class="field">
                    <label for="dari">Dari tanggal</label>
                    <input id="dari" name="dari" type="date" value="{{ $filter['dari'] }}">
                </div>

                <div class="field">
                    <label for="sampai">Sampai tanggal</label>
                    <input id="sampai" name="sampai" type="date" value="{{ $filter['sampai'] }}">
                </div>

                <button class="btn btn--primary" type="submit">Terapkan</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Daftar Janji</h2>
            <span class="badge">Halaman {{ $janji->currentPage() }} dari {{ max(1, $janji->lastPage()) }}</span>
        </div>

        <div class="tabel-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['urut' => 'tanggal', 'arah' => request('arah') === 'asc' ? 'desc' : 'asc']) }}">
                                Jadwal
                            </a>
                        </th>
                        <th>Kode</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['urut' => 'nama_pasien', 'arah' => request('arah') === 'asc' ? 'desc' : 'asc']) }}">
                                Pasien
                            </a>
                        </th>
                        <th>Dokter</th>
                        <th>Keluhan</th>
                        <th>Status</th>
                        <th>Sumber</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($janji as $a)
                        <tr>
                            <td class="nowrap">
                                <strong>{{ \Carbon\Carbon::parse($a->tanggal)->isoFormat('ddd, D MMM Y') }}</strong><br>
                                <span class="muted">{{ substr($a->jam, 0, 5) }}</span>
                            </td>
                            <td><span class="kode">{{ $a->kode_booking }}</span></td>
                            <td>
                                {{ $a->nama_pasien }}<br>
                                <span class="muted">{{ $a->telepon ?? 'tanpa telepon' }}</span>
                            </td>
                            <td>
                                {{ $a->dokter?->nama ?? '-' }}<br>
                                <span class="muted">{{ $a->dokter?->spesialisasi }}</span>
                            </td>
                            <td class="muted">{{ $a->keluhan ? \Illuminate\Support\Str::limit($a->keluhan, 60) : '-' }}</td>
                            <td><span class="pill pill--{{ $a->status }}">{{ $a->status }}</span></td>
                            <td><span class="pill pill--{{ $a->sumber }}">{{ $a->sumber }}</span></td>
                            <td>
                                <div class="aksi">
                                    @if ($a->status !== 'selesai')
                                        <form method="POST" action="{{ route('admin.janji.status', $a) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="selesai">
                                            <button class="btn-mini btn-mini--ok" type="submit">Tandai selesai</button>
                                        </form>
                                    @endif

                                    @if ($a->status !== 'batal')
                                        <form method="POST" action="{{ route('admin.janji.status', $a) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="batal">
                                            <button class="btn-mini btn-mini--danger" type="submit">Batalkan</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.janji.status', $a) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="terkonfirmasi">
                                            <button class="btn-mini" type="submit">Aktifkan lagi</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                                         stroke-linecap="round" aria-hidden="true">
                                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                                    </svg>
                                    <div>Tidak ada janji yang cocok dengan filter ini.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($janji->hasPages())
            <div class="panel__body">
                <div class="paginasi">
                    <div>Menampilkan {{ $janji->firstItem() }}–{{ $janji->lastItem() }} dari {{ $janji->total() }} janji</div>
                    <nav class="paginasi__angka" aria-label="Navigasi halaman">
                        @if ($janji->onFirstPage())
                            <span class="mati" aria-hidden="true">‹</span>
                        @else
                            <a href="{{ $janji->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">‹</a>
                        @endif

                        @foreach ($janji->getUrlRange(max(1, $janji->currentPage() - 2), min($janji->lastPage(), $janji->currentPage() + 2)) as $hal => $url)
                            @if ($hal === $janji->currentPage())
                                <span class="aktif" aria-current="page">{{ $hal }}</span>
                            @else
                                <a href="{{ $url }}">{{ $hal }}</a>
                            @endif
                        @endforeach

                        @if ($janji->hasMorePages())
                            <a href="{{ $janji->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">›</a>
                        @else
                            <span class="mati" aria-hidden="true">›</span>
                        @endif
                    </nav>
                </div>
            </div>
        @endif
    </section>
@endsection
