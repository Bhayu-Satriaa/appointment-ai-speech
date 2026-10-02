<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\ClinicAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveController extends Controller
{
    private const AUTH_TOKENS_URL = 'https://generativelanguage.googleapis.com/v1beta/auth_tokens';

    private const WS_URL = 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained';

    public function __construct(
        private ClinicAgent $agent,
        private BookingService $booking
    ) {}

    /** Konfigurasi yang dipakai browser untuk membuka sesi suara */
    public function konfigurasi(): JsonResponse
    {
        return response()->json([
            'ok' => (string) config('services.gemini.api_key') !== '',
            'model' => (string) config('services.gemini.model'),
            'voice' => (string) config('services.gemini.voice'),
            'ws_url' => self::WS_URL,
            'system_instruction' => $this->agent->systemPrompt(),
            'tools' => $this->toolsGemini(),
            'hari_ini' => $this->booking->hariIni(),
        ]);
    }

    /**
     * Membuat ephemeral token.
     * API key asli tidak pernah dikirim ke browser — hanya token berumur pendek ini.
     */
    public function token(): JsonResponse
    {
        $key = (string) config('services.gemini.api_key');
        if ($key === '') {
            return response()->json(['ok' => false, 'error' => 'GEMINI_API_KEY belum diisi di .env'], 422);
        }

        // Waktu kedaluwarsa dihitung dari jam KOMPUTER INI. Kalau jamnya melenceng
        // dari jam Google, token bisa dianggap sudah kedaluwarsa sejak dibuat —
        // gejalanya "token expired" padahal key-nya benar. Karena itu secara bawaan
        // kita tidak mengirim waktu sama sekali dan membiarkan Google memakai
        // nilai bawaannya, yang dihitung dari jam mereka sendiri.
        $muatan = ['uses' => 1];

        if (config('services.gemini.kirim_waktu_token')) {
            $kedaluwarsa = Carbon::now()->addMinutes(30)->utc()->format('Y-m-d\TH:i:s\Z');
            $batasSesiBaru = Carbon::now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z');
            $muatan['expireTime'] = $kedaluwarsa;
            $muatan['newSessionExpireTime'] = $batasSesiBaru;
        }

        try {
            $respon = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(30)
                ->acceptJson()
                ->post(self::AUTH_TOKENS_URL, $muatan);

            if (! $respon->successful()) {
                Log::warning('Gagal membuat ephemeral token', [
                    'status' => $respon->status(),
                    'body' => mb_substr($respon->body(), 0, 400),
                ]);

                return response()->json([
                    'ok' => false,
                    'error' => 'Google menolak permintaan token (HTTP '.$respon->status().')',
                ], 502);
            }

            $data = $respon->json();

            return response()->json([
                'ok' => true,
                'token' => $data['name'] ?? null,
                'model' => (string) config('services.gemini.model'),
                'voice' => (string) config('services.gemini.voice'),
                'ws_url' => self::WS_URL,
                'berlaku_sampai' => $data['expireTime'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Exception ephemeral token', ['pesan' => $e->getMessage()]);

            return response()->json(['ok' => false, 'error' => 'Gagal menghubungi Google: '.$e->getMessage()], 502);
        }
    }

    /**
     * Menjalankan tool yang diminta AI saat percakapan suara.
     * POST /api/tool  { nama: "cek_jadwal", args: {...} }
     */
    public function jalankanTool(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string'],
            'args' => ['array'],
        ]);

        $hasil = $this->agent->jalankanTool($data['nama'], $data['args'] ?? [], 'suara');

        return response()->json([
            'hasil' => $hasil,
            'janji_mendatang' => $this->booking->janjiMendatang(),
        ]);
    }

    // ------------------------------------------------------------------
    // Konversi format tool: OpenAI -> Gemini
    // ------------------------------------------------------------------

    private function toolsGemini(): array
    {
        $deklarasi = [];
        foreach ($this->agent->tools() as $tool) {
            $f = $tool['function'] ?? [];
            if (! isset($f['name'])) {
                continue;
            }
            $deklarasi[] = [
                'name' => $f['name'],
                'description' => $f['description'] ?? '',
                'parameters' => $this->skemaGemini($f['parameters'] ?? []),
            ];
        }

        return [['functionDeclarations' => $deklarasi]];
    }

    private function skemaGemini(array $skema): array
    {
        $keluar = [];
        foreach ($skema as $kunci => $nilai) {
            switch ($kunci) {
                case 'type':
                    $keluar['type'] = strtoupper((string) $nilai);
                    break;
                case 'properties':
                    $props = [];
                    foreach ((array) $nilai as $nama => $isi) {
                        $props[$nama] = $this->skemaGemini((array) $isi);
                    }
                    $keluar['properties'] = $props;
                    break;
                case 'items':
                    $keluar['items'] = $this->skemaGemini((array) $nilai);
                    break;
                case 'required':
                case 'enum':
                case 'description':
                    $keluar[$kunci] = $nilai;
                    break;
            }
        }

        return $keluar;
    }
}
