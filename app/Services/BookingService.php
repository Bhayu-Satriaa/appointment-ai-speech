<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Dokter;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingService
{
    private const NAMA_HARI = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Info tanggal yang sudah manusiawi.
     *
     * PENTING: hasil ini selalu dikirim balik ke AI lewat tool, supaya AI
     * TIDAK menghitung sendiri nama hari (dari pengujian, model sering salah).
     */
    public function infoTanggal(Carbon $tgl): array
    {
        return [
            'tanggal' => $tgl->format('Y-m-d'),
            'hari' => self::NAMA_HARI[$tgl->dayOfWeekIso] ?? '-',
            'tanggal_lengkap' => $tgl->day . ' ' . (self::NAMA_BULAN[$tgl->month] ?? '') . ' ' . $tgl->year,
        ];
    }

    public function hariIni(): array
    {
        return $this->infoTanggal(Carbon::today());
    }

    /** @return array<int, array> daftar dokter aktif */
    public function daftarDokter(): array
    {
        return Dokter::where('aktif', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Dokter $d) => [
                'id' => $d->id,
                'nama' => $d->nama,
                'spesialisasi' => $d->spesialisasi,
                'jam_praktik' => substr($d->jam_mulai, 0, 5) . '-' . substr($d->jam_selesai, 0, 5),
                'hari_praktik' => implode(', ', array_map(
                    fn ($h) => self::NAMA_HARI[$h] ?? $h,
                    $d->hariPraktikList()
                )),
            ])
            ->all();
    }

    /** Cari dokter dari kata kunci bebas ("gigi", "drg. Sari", "anak") */
    public function cariDokter(?string $kataKunci): ?Dokter
    {
        $semua = Dokter::where('aktif', true)->get();
        if ($semua->isEmpty()) {
            return null;
        }
        if (! $kataKunci) {
            return $semua->first();
        }

        $kunci = Str::lower($kataKunci);
        // buang gelar umum supaya pencocokan lebih longgar
        $kunci = str_replace(['drg.', 'dr.', 'sp.a', 'sp.a.'], '', $kunci);
        $kunci = trim(preg_replace('/\s+/', ' ', $kunci));

        $terbaik = null;
        $skorTerbaik = 0;
        foreach ($semua as $d) {
            $nama = Str::lower($d->nama);
            $spes = Str::lower($d->spesialisasi);
            $skor = 0;
            if ($kunci !== '' && str_contains($nama, $kunci)) {
                $skor += 5;
            }
            foreach (explode(' ', $kunci) as $potongan) {
                if (strlen($potongan) < 3) {
                    continue;
                }
                if (str_contains($nama, $potongan)) {
                    $skor += 3;
                }
                if (str_contains($spes, $potongan)) {
                    $skor += 2;
                }
            }
            if ($skor > $skorTerbaik) {
                $skorTerbaik = $skor;
                $terbaik = $d;
            }
        }

        return $terbaik ?? $semua->first();
    }

    /**
     * Slot yang masih kosong untuk dokter & tanggal tertentu.
     * Mengembalikan [] kalau dokter tidak praktik hari itu atau tanggalnya sudah lewat.
     */
    public function slotTersedia(Dokter $dokter, Carbon $tanggal): array
    {
        $info = $this->infoTanggal($tanggal);

        if (! $dokter->praktikPada($tanggal->dayOfWeekIso)) {
            return [
                'praktik' => false,
                'alasan' => "{$dokter->nama} tidak praktik pada hari {$info['hari']}",
                'slot' => [],
            ];
        }

        if ($tanggal->isPast() && ! $tanggal->isToday()) {
            return [
                'praktik' => false,
                'alasan' => 'Tanggal tersebut sudah lewat',
                'slot' => [],
            ];
        }

        $terpakai = Appointment::where('dokter_id', $dokter->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->where('status', '!=', 'batal')
            ->pluck('jam')
            ->map(fn ($j) => substr($j, 0, 5))
            ->all();

        $mulai = Carbon::parse($tanggal->toDateString() . ' ' . $dokter->jam_mulai);
        $selesai = Carbon::parse($tanggal->toDateString() . ' ' . $dokter->jam_selesai);
        $langkah = max(5, (int) $dokter->durasi_slot);

        $slot = [];
        $kursor = $mulai->copy();
        while ($kursor->lt($selesai)) {
            $jam = $kursor->format('H:i');
            if (! in_array($jam, $terpakai, true)) {
                $slot[] = $jam;
            }
            $kursor->addMinutes($langkah);
        }

        return [
            'praktik' => true,
            'slot' => $slot,
            'jumlah_slot_kosong' => count($slot),
        ];
    }

    /**
     * Ringkasan slot KOSONG beberapa hari ke depan, untuk disuntikkan ke prompt.
     *
     * Memangkas satu putaran tool: tanpa ini, setiap pertanyaan ketersediaan
     * memaksa model memanggil cek_jadwal lebih dulu, dan tiap putaran berarti
     * satu panggilan LLM penuh — yang langsung menambah waktu tunggu.
     */
    public function ringkasKetersediaan(int $hari = 3, int $maksSlot = 10): string
    {
        $baris = [];

        for ($i = 0; $i < $hari; $i++) {
            $tanggal = now()->startOfDay()->addDays($i);
            $info = $this->infoTanggal($tanggal);

            foreach ($this->daftarDokter() as $ringkas) {
                $dokter = Dokter::find($ringkas['id'] ?? null);
                if (! $dokter) {
                    continue;
                }

                $hasil = $this->slotTersedia($dokter, $tanggal->copy());
                if (! ($hasil['praktik'] ?? false) || empty($hasil['slot'])) {
                    continue;
                }

                $slot = array_slice($hasil['slot'], 0, $maksSlot);
                $sisa = count($hasil['slot']) - count($slot);

                $baris[] = sprintf(
                    '%s (%s) | %s | %s%s',
                    $tanggal->toDateString(),
                    $info['hari'],
                    $dokter->nama,
                    implode(', ', $slot),
                    $sisa > 0 ? " +{$sisa} slot lain" : ''
                );
            }
        }

        return $baris
            ? implode("\n", $baris)
            : '(tidak ada jadwal praktik dalam '.$hari.' hari ke depan)';
    }

    /** Buat janji temu baru. Mengembalikan array hasil siap dikirim ke AI. */
    public function buatJanji(
        string $namaPasien,
        ?string $kataKunciDokter,
        string $tanggal,
        string $jam,
        ?string $keluhan = null,
        ?string $telepon = null,
        string $sumber = 'chat'
    ): array {
        $dokter = $this->cariDokter($kataKunciDokter);
        if (! $dokter) {
            return ['sukses' => false, 'pesan' => 'Dokter tidak ditemukan.'];
        }

        try {
            $tgl = Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay();
        } catch (\Throwable $e) {
            return ['sukses' => false, 'pesan' => "Format tanggal tidak dikenali: {$tanggal}. Gunakan YYYY-MM-DD."];
        }

        $info = $this->infoTanggal($tgl);
        if (! $dokter->praktikPada($tgl->dayOfWeekIso)) {
            return [
                'sukses' => false,
                'pesan' => "{$dokter->nama} tidak praktik pada hari {$info['hari']} ({$info['tanggal_lengkap']}).",
                'info_tanggal' => $info,
            ];
        }

        $jam = $this->normalisasiJam($jam);
        if ($jam === null) {
            return ['sukses' => false, 'pesan' => "Format jam tidak dikenali: {$jam}. Gunakan HH:MM (24 jam)."];
        }

        $slot = $this->slotTersedia($dokter, $tgl);
        if (! in_array($jam, $slot['slot'] ?? [], true)) {
            return [
                'sukses' => false,
                'pesan' => "Jam {$jam} tidak tersedia untuk {$dokter->nama} pada {$info['hari']}, {$info['tanggal_lengkap']}.",
                'jam_tersedia' => $slot['slot'] ?? [],
                'info_tanggal' => $info,
            ];
        }

        $appointment = Appointment::create([
            'kode_booking' => $this->kodeBookingBaru(),
            'dokter_id' => $dokter->id,
            'nama_pasien' => $namaPasien,
            'telepon' => $telepon,
            'tanggal' => $tgl->toDateString(),
            'jam' => $jam . ':00',
            'keluhan' => $keluhan,
            'status' => 'terkonfirmasi',
            'sumber' => $sumber,
        ]);

        return [
            'sukses' => true,
            'pesan' => 'Janji temu berhasil dibuat.',
            'kode_booking' => $appointment->kode_booking,
            'nama_pasien' => $namaPasien,
            'dokter' => $dokter->nama,
            'spesialisasi' => $dokter->spesialisasi,
            'tanggal' => $info['tanggal'],
            'hari' => $info['hari'],
            'tanggal_lengkap' => $info['tanggal_lengkap'],
            'jam' => $jam,
        ];
    }

    public function cekJanji(string $kode): array
    {
        $a = Appointment::with('dokter')->where('kode_booking', strtoupper(trim($kode)))->first();
        if (! $a) {
            return ['ditemukan' => false, 'pesan' => "Tidak ada janji dengan kode {$kode}."];
        }
        $info = $this->infoTanggal(Carbon::parse($a->tanggal));

        return [
            'ditemukan' => true,
            'kode_booking' => $a->kode_booking,
            'nama_pasien' => $a->nama_pasien,
            'dokter' => $a->dokter?->nama,
            'hari' => $info['hari'],
            'tanggal_lengkap' => $info['tanggal_lengkap'],
            'jam' => substr($a->jam, 0, 5),
            'status' => $a->status,
        ];
    }

    public function batalJanji(string $kode): array
    {
        $a = Appointment::where('kode_booking', strtoupper(trim($kode)))->first();
        if (! $a) {
            return ['sukses' => false, 'pesan' => "Tidak ada janji dengan kode {$kode}."];
        }
        if ($a->status === 'batal') {
            return ['sukses' => false, 'pesan' => "Janji {$a->kode_booking} sudah dibatalkan sebelumnya."];
        }
        $a->update(['status' => 'batal']);

        return ['sukses' => true, 'pesan' => "Janji {$a->kode_booking} berhasil dibatalkan."];
    }

    public function jadwalHariIni(): array
    {
        return Appointment::with('dokter')
            ->whereDate('tanggal', Carbon::today()->toDateString())
            ->orderBy('jam')
            ->get()
            ->map(fn (Appointment $a) => [
                'kode_booking' => $a->kode_booking,
                'nama_pasien' => $a->nama_pasien,
                'dokter' => $a->dokter?->nama,
                'jam' => substr($a->jam, 0, 5),
                'status' => $a->status,
                'sumber' => $a->sumber,
            ])
            ->all();
    }

    /**
     * Janji yang akan datang (hari ini dan seterusnya).
     * Dipakai panel bukti di UI — dibaca langsung dari database, bukan dari
     * ingatan AI, sehingga terlihat jelas bahwa janji benar-benar tersimpan.
     */
    public function janjiMendatang(int $batas = 15): array
    {
        return Appointment::with('dokter')
            ->whereDate('tanggal', '>=', Carbon::today()->toDateString())
            ->where('status', '!=', 'batal')
            ->orderBy('tanggal')
            ->orderBy('jam')
            ->limit($batas)
            ->get()
            ->map(function (Appointment $a) {
                $info = $this->infoTanggal(Carbon::parse($a->tanggal));

                return [
                    'kode_booking' => $a->kode_booking,
                    'nama_pasien' => $a->nama_pasien,
                    'dokter' => $a->dokter?->nama,
                    'hari' => $info['hari'],
                    'tanggal' => $info['tanggal'],
                    'tanggal_lengkap' => $info['tanggal_lengkap'],
                    'jam' => substr($a->jam, 0, 5),
                    'keluhan' => $a->keluhan,
                    'status' => $a->status,
                    'sumber' => $a->sumber,
                ];
            })
            ->all();
    }

    private function normalisasiJam(string $jam): ?string
    {
        $jam = trim(str_replace('.', ':', $jam));
        foreach (['H:i', 'H:i:s', 'G:i', 'G'] as $format) {
            try {
                return Carbon::createFromFormat($format, $jam)->format('H:i');
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private function kodeBookingBaru(): string
    {
        do {
            $kode = 'BK' . strtoupper(Str::random(6));
        } while (Appointment::where('kode_booking', $kode)->exists());

        return $kode;
    }
}
