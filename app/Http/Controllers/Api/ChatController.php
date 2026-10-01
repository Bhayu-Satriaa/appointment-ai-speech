<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\ClinicAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ClinicAgent $agent,
        private BookingService $booking
    ) {}

    /** POST /api/chat  { pesan: string, riwayat: [{role, content}], sumber?: "chat"|"suara" } */
    public function kirim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pesan' => ['required', 'string', 'max:2000'],
            'riwayat' => ['array'],
            'riwayat.*.role' => ['required', 'in:user,assistant'],
            'riwayat.*.content' => ['required', 'string'],
            'sumber' => ['nullable', 'in:chat,suara'],
        ]);

        $riwayat = $data['riwayat'] ?? [];
        $riwayat[] = ['role' => 'user', 'content' => $data['pesan']];

        $hasil = $this->agent->balas($riwayat, $data['sumber'] ?? 'chat');

        return response()->json([
            'balasan' => $hasil['balasan'],
            'tool' => $hasil['tool'],
            'jadwal_hari_ini' => $this->booking->jadwalHariIni(),
            'janji_mendatang' => $this->booking->janjiMendatang(),
        ]);
    }

    /** GET /api/dokter */
    public function dokter(): JsonResponse
    {
        return response()->json([
            'hari_ini' => $this->booking->hariIni(),
            'dokter' => $this->booking->daftarDokter(),
        ]);
    }

    /** GET /api/jadwal */
    public function jadwal(): JsonResponse
    {
        return response()->json([
            'hari_ini' => $this->booking->hariIni(),
            'jadwal' => $this->booking->jadwalHariIni(),
            'janji_mendatang' => $this->booking->janjiMendatang(),
        ]);
    }
}
