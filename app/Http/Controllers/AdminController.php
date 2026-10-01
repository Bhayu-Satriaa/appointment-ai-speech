<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Dokter;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    private const PER_HALAMAN = 15;

    private const DAFTAR_STATUS = ['terkonfirmasi', 'selesai', 'batal'];

    public function __construct(private BookingService $booking) {}

    /** Ringkasan operasional klinik. */
    public function dashboard(): View
    {
        $hariIni = Carbon::today();
        $mingguDepan = Carbon::today()->addDays(7);

        $statistik = [
            'total' => Appointment::count(),
            'hari_ini' => Appointment::whereDate('tanggal', $hariIni)->where('status', '!=', 'batal')->count(),
            'tujuh_hari' => Appointment::whereBetween('tanggal', [$hariIni, $mingguDepan])
                ->where('status', '!=', 'batal')->count(),
            'selesai' => Appointment::where('status', 'selesai')->count(),
            'batal' => Appointment::where('status', 'batal')->count(),
            'lewat_suara' => Appointment::where('sumber', 'suara')->count(),
        ];

        $perDokter = Dokter::withCount('appointments')
            ->orderByDesc('appointments_count')
            ->get();

        $terbaru = Appointment::with('dokter')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // Jumlah janji per hari untuk 7 hari ke depan
        $harian = Appointment::select(DB::raw('DATE(tanggal) as tgl'), DB::raw('COUNT(*) as jumlah'))
            ->whereBetween('tanggal', [$hariIni, $mingguDepan])
            ->where('status', '!=', 'batal')
            ->groupBy('tgl')
            ->orderBy('tgl')
            ->pluck('jumlah', 'tgl')
            ->all();

        return view('admin.dashboard', [
            'statistik' => $statistik,
            'perDokter' => $perDokter,
            'terbaru' => $terbaru,
            'harian' => $harian,
            'hariIni' => $this->booking->infoTanggal($hariIni),
        ]);
    }

    /** Daftar janji dengan filter, pencarian, dan paginasi. */
    public function janji(Request $request): View
    {
        $filter = [
            'cari' => trim((string) $request->input('cari', '')),
            'dokter_id' => $request->input('dokter_id'),
            'status' => $request->input('status'),
            'sumber' => $request->input('sumber'),
            'dari' => $request->input('dari'),
            'sampai' => $request->input('sampai'),
        ];

        $query = Appointment::with('dokter');

        if ($filter['cari'] !== '') {
            $kata = $filter['cari'];
            $query->where(function ($q) use ($kata) {
                $q->where('nama_pasien', 'like', "%{$kata}%")
                    ->orWhere('kode_booking', 'like', "%{$kata}%")
                    ->orWhere('telepon', 'like', "%{$kata}%")
                    ->orWhere('keluhan', 'like', "%{$kata}%");
            });
        }
        if ($filter['dokter_id']) {
            $query->where('dokter_id', $filter['dokter_id']);
        }
        if ($filter['status']) {
            $query->where('status', $filter['status']);
        }
        if ($filter['sumber']) {
            $query->where('sumber', $filter['sumber']);
        }
        if ($filter['dari']) {
            $query->whereDate('tanggal', '>=', $filter['dari']);
        }
        if ($filter['sampai']) {
            $query->whereDate('tanggal', '<=', $filter['sampai']);
        }

        $urut = (string) $request->input('urut', 'tanggal');
        $arah = $request->input('arah') === 'asc' ? 'asc' : 'desc';
        $kolomUrut = in_array($urut, ['tanggal', 'nama_pasien', 'created_at', 'status'], true) ? $urut : 'tanggal';
        $query->orderBy($kolomUrut, $arah)->orderBy('jam', $arah);

        return view('admin.janji', [
            'janji' => $query->paginate(self::PER_HALAMAN)->withQueryString(),
            'filter' => $filter,
            'daftarDokter' => Dokter::orderBy('nama')->get(),
            'daftarStatus' => self::DAFTAR_STATUS,
            'hariIni' => $this->booking->infoTanggal(Carbon::today()),
        ]);
    }

    /** Ubah status satu janji (konfirmasi / selesai / batal). */
    public function ubahStatus(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'status' => ['required', 'in:terkonfirmasi,selesai,batal'],
        ]);

        $appointment->update(['status' => $data['status']]);

        return redirect()
            ->back()
            ->with('sukses', "Status janji {$appointment->kode_booking} diubah menjadi \"{$data['status']}\".");
    }

    /** Daftar dokter beserta jumlah janjinya. */
    public function dokter(): View
    {
        $dokter = Dokter::withCount([
            'appointments',
            'appointments as janji_aktif' => fn ($q) => $q->where('status', '!=', 'batal'),
        ])->orderBy('nama')->get();

        return view('admin.dokter', [
            'dokter' => $dokter,
            'hariIni' => $this->booking->infoTanggal(Carbon::today()),
        ]);
    }

    /** Unduh data janji sebagai CSV (untuk laporan). */
    public function export(Request $request): StreamedResponse
    {
        $query = Appointment::with('dokter')->orderBy('tanggal')->orderBy('jam');

        if ($request->filled('dari')) {
            $query->whereDate('tanggal', '>=', $request->input('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal', '<=', $request->input('sampai'));
        }
        if ($request->filled('dokter_id')) {
            $query->where('dokter_id', $request->input('dokter_id'));
        }

        $namaFile = 'janji-temu-'.Carbon::now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $keluar = fopen('php://output', 'w');
            fputcsv($keluar, ['Kode', 'Tanggal', 'Jam', 'Pasien', 'Telepon', 'Dokter', 'Spesialisasi', 'Keluhan', 'Status', 'Sumber']);

            $query->chunk(200, function ($baris) use ($keluar) {
                foreach ($baris as $a) {
                    fputcsv($keluar, [
                        $a->kode_booking,
                        Carbon::parse($a->tanggal)->format('Y-m-d'),
                        substr($a->jam, 0, 5),
                        $a->nama_pasien,
                        $a->telepon ?? '-',
                        $a->dokter?->nama ?? '-',
                        $a->dokter?->spesialisasi ?? '-',
                        $a->keluhan ?? '-',
                        $a->status,
                        $a->sumber,
                    ]);
                }
            });

            fclose($keluar);
        }, $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
