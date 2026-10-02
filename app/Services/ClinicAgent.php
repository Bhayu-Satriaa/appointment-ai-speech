<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agen percakapan klinik.
 *
 * Alur: pesan user -> dikirim ke LLM -> LLM memutuskan memanggil tool ->
 * tool dijalankan lewat BookingService -> hasilnya dikirim balik ke LLM ->
 * LLM menyusun jawaban akhir untuk pasien.
 */
class ClinicAgent
{
    private const MAKS_PUTARAN = 6;

    public function __construct(private BookingService $booking) {}

    /**
     * @param  array  $riwayat  [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @return array{balasan: string, tool: array, sumber: string}
     */
    public function balas(array $riwayat, string $sumber = 'chat'): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            $riwayat
        );

        $jejakTool = [];

        for ($putaran = 0; $putaran < self::MAKS_PUTARAN; $putaran++) {
            $respon = $this->kirimKeLlm($messages);

            if (! $respon['ok']) {
                return [
                    'balasan' => $this->petunjukKesalahan((string) $respon['error']),
                    'tool' => $jejakTool,
                    'sumber' => $sumber,
                    'error' => $respon['error'],
                ];
            }

            $pesan = $respon['pesan'];
            $toolCalls = $pesan['tool_calls'] ?? [];

            // Tidak ada tool call -> ini jawaban akhir
            if (empty($toolCalls)) {
                return [
                    'balasan' => (string) ($pesan['content'] ?? ''),
                    'tool' => $jejakTool,
                    'sumber' => $sumber,
                ];
            }

            // Simpan permintaan tool dari AI ke riwayat percakapan
            $messages[] = $pesan;

            foreach ($toolCalls as $tc) {
                $nama = $tc['function']['name'] ?? 'tidak_dikenal';
                $args = json_decode($tc['function']['arguments'] ?? '{}', true);
                if (! is_array($args)) {
                    $args = [];
                }

                $hasil = $this->jalankanTool($nama, $args, $sumber);
                $jejakTool[] = ['nama' => $nama, 'args' => $args, 'hasil' => $hasil];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc['id'] ?? 'call_'.$putaran,
                    'content' => json_encode($hasil, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return [
            'balasan' => 'Maaf, percakapan ini terlalu panjang. Bisa diulang lebih singkat?',
            'tool' => $jejakTool,
            'sumber' => $sumber,
        ];
    }

    // ------------------------------------------------------------------
    // LLM
    // ------------------------------------------------------------------

    private function kirimKeLlm(array $messages): array
    {
        $model = (string) config('services.llm.model');
        $cadangan = (string) config('services.llm.model_fallback');

        $hasil = $this->panggilModel($messages, $model);

        // Kalau model utama gagal (mis. penyedia sedang gangguan), coba model cadangan
        if (! $hasil['ok'] && $cadangan !== '' && $cadangan !== $model) {
            Log::info('Model utama gagal, mencoba cadangan', ['cadangan' => $cadangan, 'error' => $hasil['error']]);
            $hasilCadangan = $this->panggilModel($messages, $cadangan);

            if ($hasilCadangan['ok']) {
                $hasilCadangan['model_dipakai'] = $cadangan;

                return $hasilCadangan;
            }

            $hasil['error'] = $hasil['error'].' | cadangan juga gagal: '.$hasilCadangan['error'];
        }

        return $hasil;
    }

    /** Satu percobaan panggilan ke model. */
    private function panggilModel(array $messages, string $model): array
    {
        $base = rtrim((string) config('services.llm.base_url'), '/');
        $key = (string) config('services.llm.api_key');

        if ($base === '' || $key === '' || $model === '') {
            return ['ok' => false, 'error' => 'Konfigurasi LLM belum lengkap (LLM_BASE_URL / LLM_API_KEY / LLM_MODEL).'];
        }

        try {
            $respon = Http::withToken($key)
                ->timeout(90)
                ->acceptJson()
                ->post($base.'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'tools' => $this->tools(),
                    'tool_choice' => 'auto',
                    'temperature' => 0.3,
                    // Batasi panjang jawaban: waktu tunggu sebanding dengan
                    // jumlah token yang dihasilkan model.
                    'max_tokens' => (int) config('services.llm.max_tokens', 300),
                ]);

            if (! $respon->successful()) {
                Log::warning('LLM gagal', ['status' => $respon->status(), 'body' => mb_substr($respon->body(), 0, 500)]);

                return ['ok' => false, 'error' => 'HTTP '.$respon->status().': '.mb_substr($respon->body(), 0, 300)];
            }

            $data = $respon->json();
            $pesan = $data['choices'][0]['message'] ?? null;
            if (! is_array($pesan)) {
                return ['ok' => false, 'error' => 'Format respons LLM tidak dikenali.'];
            }

            return ['ok' => true, 'pesan' => $pesan];
        } catch (\Throwable $e) {
            Log::error('LLM exception', ['pesan' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mengubah kegagalan panggilan model menjadi petunjuk yang bisa langsung
     * dikerjakan. Penting bagi siapa pun yang baru meng-clone: tanpa ini,
     * kesalahan konfigurasi hanya tampil sebagai "sistem sedang ada gangguan"
     * dan penyebabnya harus dicari sendiri di log.
     */
    private function petunjukKesalahan(string $galat): string
    {
        $g = mb_strtolower($galat);

        if (str_contains($g, 'expired') || str_contains($g, 'kadaluarsa')) {
            return 'Kunci API model sudah kedaluwarsa. Ganti LLM_API_KEY di file .env.';
        }

        if (str_contains($g, '401') || str_contains($g, '403') || str_contains($g, 'unauthorized')
            || str_contains($g, 'api key') || str_contains($g, 'invalid_api_key')) {
            return 'Kunci API model ditolak. Periksa LLM_API_KEY dan LLM_BASE_URL di file .env.';
        }

        if (str_contains($g, '404') || str_contains($g, 'model_not_found') || str_contains($g, 'not found')) {
            return 'Model tidak ditemukan. Periksa LLM_MODEL dan LLM_BASE_URL di file .env.';
        }

        if (str_contains($g, '429') || str_contains($g, 'quota') || str_contains($g, 'rate limit')) {
            return 'Kuota penyedia model sedang habis. Coba lagi beberapa saat lagi.';
        }

        if (str_contains($g, 'could not resolve') || str_contains($g, 'connection refused')
            || str_contains($g, 'curl error 7') || str_contains($g, 'failed to connect')) {
            return 'Tidak bisa menghubungi penyedia model. Periksa LLM_BASE_URL di file .env — '
                .'alamat seperti 127.0.0.1 hanya berlaku di komputer tempat server itu berjalan.';
        }

        if (str_contains($g, 'timed out') || str_contains($g, 'timeout') || str_contains($g, 'curl error 28')) {
            return 'Penyedia model tidak merespons tepat waktu. Coba lagi.';
        }

        if (preg_match('/\b5\d\d\b/', $g)) {
            return 'Penyedia model sedang bermasalah (server mereka). Coba lagi sebentar lagi.';
        }

        return 'Maaf, sistem sedang ada gangguan. Coba lagi sebentar ya.';
    }

    public function systemPrompt(): string
    {
        $hariIni = $this->booking->infoTanggal(now());
        $daftarDokter = json_encode($this->booking->daftarDokter(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ketersediaan = $this->booking->ringkasKetersediaan(3);

        return <<<PROMPT
        Kamu adalah Rani, resepsionis digital Klinik Sehat Bersama.

        GAYA BICARA:
        - Selalu gunakan Bahasa Indonesia yang ramah, sopan, dan singkat.
        - Sapa pasien dengan hangat, jangan bertele-tele.
        - Maksimal 3 kalimat per balasan, kecuali sedang menyebutkan daftar jadwal.

        ATURAN PENTING:
        1. Hari ini adalah {$hariIni['hari']}, {$hariIni['tanggal_lengkap']} ({$hariIni['tanggal']}).
           JANGAN menghitung sendiri nama hari atau tanggal. Selalu ambil dari hasil tool.
        2. JANGAN PERNAH mengarang ketersediaan jadwal.
           - Untuk tanggal yang sudah tercantum di bagian KETERSEDIAAN di bawah, langsung
             pakai data itu. TIDAK perlu memanggil cek_jadwal.
           - Untuk tanggal di luar rentang itu, WAJIB panggil cek_jadwal dulu.
        3. SEBELUM memanggil buat_janji, kamu WAJIB memastikan sudah tahu: nama pasien, dokter,
           tanggal, dan jam. Kalau ada yang belum jelas, TANYAKAN dulu.
        4. SEBELUM membuat janji, ulangi detailnya dan minta pasien mengonfirmasi.
           Jangan langsung membuat janji sebelum pasien bilang setuju.
        5. Kalau jam yang diminta tidak tersedia, tawarkan 2-3 jam terdekat dari daftar slot
           yang tersedia.
        6. Kalau pasien menyebut "besok", "lusa", atau "hari Senin", ubah ke format YYYY-MM-DD
           berdasarkan tanggal hari ini di atas, lalu tetap verifikasi hasilnya lewat tool.
        7. Jangan pernah menyebut kode booking palsu. Kode booking hanya boleh diambil dari
           hasil tool buat_janji.
        8. Kalau pasien membatalkan, minta kode booking-nya, lalu panggil batal_janji.

        DAFTAR DOKTER DI KLINIK:
        {$daftarDokter}

        KETERSEDIAAN (dihitung sistem, format: tanggal (hari) | dokter | jam kosong):
        {$ketersediaan}
        PROMPT;
    }

    // ------------------------------------------------------------------
    // TOOL
    // ------------------------------------------------------------------

    public function tools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cek_jadwal',
                    'description' => 'Mengecek jam praktik dokter yang masih kosong pada tanggal tertentu. Wajib dipanggil sebelum menawarkan jam ke pasien.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dokter' => ['type' => 'string', 'description' => 'Nama atau spesialisasi dokter, contoh: "dokter gigi", "drg. Sari", "anak"'],
                            'tanggal' => ['type' => 'string', 'description' => 'Tanggal dalam format YYYY-MM-DD'],
                        ],
                        'required' => ['dokter', 'tanggal'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buat_janji',
                    'description' => 'Membuat janji temu baru. Hanya panggil setelah pasien mengonfirmasi nama, dokter, tanggal, dan jam.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nama_pasien' => ['type' => 'string', 'description' => 'Nama lengkap pasien'],
                            'dokter' => ['type' => 'string', 'description' => 'Nama atau spesialisasi dokter'],
                            'tanggal' => ['type' => 'string', 'description' => 'Tanggal format YYYY-MM-DD'],
                            'jam' => ['type' => 'string', 'description' => 'Jam format HH:MM (24 jam)'],
                            'keluhan' => ['type' => 'string', 'description' => 'Keluhan pasien, opsional'],
                            'telepon' => ['type' => 'string', 'description' => 'Nomor telepon pasien, opsional'],
                        ],
                        'required' => ['nama_pasien', 'dokter', 'tanggal', 'jam'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cek_janji',
                    'description' => 'Melihat detail janji temu berdasarkan kode booking.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'kode_booking' => ['type' => 'string', 'description' => 'Kode booking, contoh BK1A2B3C'],
                        ],
                        'required' => ['kode_booking'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'batal_janji',
                    'description' => 'Membatalkan janji temu berdasarkan kode booking.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'kode_booking' => ['type' => 'string'],
                        ],
                        'required' => ['kode_booking'],
                    ],
                ],
            ],
        ];
    }

    public function jalankanTool(string $nama, array $args, string $sumber = 'suara'): array
    {
        return match ($nama) {
            'cek_jadwal' => $this->toolCekJadwal($args),
            'buat_janji' => $this->toolBuatJanji($args, $sumber),
            'cek_janji' => $this->booking->cekJanji((string) ($args['kode_booking'] ?? '')),
            'batal_janji' => $this->booking->batalJanji((string) ($args['kode_booking'] ?? '')),
            default => ['sukses' => false, 'pesan' => "Tool {$nama} tidak dikenal."],
        };
    }

    private function toolCekJadwal(array $args): array
    {
        $dokter = $this->booking->cariDokter($args['dokter'] ?? null);
        if (! $dokter) {
            return ['sukses' => false, 'pesan' => 'Dokter tidak ditemukan.'];
        }

        try {
            $tanggal = \Carbon\Carbon::createFromFormat('Y-m-d', (string) ($args['tanggal'] ?? ''))->startOfDay();
        } catch (\Throwable $e) {
            return ['sukses' => false, 'pesan' => 'Format tanggal harus YYYY-MM-DD.'];
        }

        $hasil = $this->booking->slotTersedia($dokter, $tanggal);
        $info = $this->booking->infoTanggal($tanggal);

        return array_merge([
            'sukses' => true,
            'dokter' => $dokter->nama,
            'spesialisasi' => $dokter->spesialisasi,
            'tanggal' => $info['tanggal'],
            'hari' => $info['hari'],
            'tanggal_lengkap' => $info['tanggal_lengkap'],
        ], $hasil);
    }

    private function toolBuatJanji(array $args, string $sumber): array
    {
        return $this->booking->buatJanji(
            namaPasien: (string) ($args['nama_pasien'] ?? ''),
            kataKunciDokter: $args['dokter'] ?? null,
            tanggal: (string) ($args['tanggal'] ?? ''),
            jam: (string) ($args['jam'] ?? ''),
            keluhan: $args['keluhan'] ?? null,
            telepon: $args['telepon'] ?? null,
            sumber: $sumber,
        );
    }
}
