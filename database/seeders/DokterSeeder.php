<?php

namespace Database\Seeders;

use App\Models\Dokter;
use Illuminate\Database\Seeder;

class DokterSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nama' => 'dr. Andi Prasetyo',
                'spesialisasi' => 'Dokter Umum',
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '12:00:00',
                'durasi_slot' => 30,
                'hari_praktik' => '1,2,3,4,5', // Senin - Jumat
            ],
            [
                'nama' => 'drg. Sari Wulandari',
                'spesialisasi' => 'Dokter Gigi',
                'jam_mulai' => '13:00:00',
                'jam_selesai' => '17:00:00',
                'durasi_slot' => 30,
                'hari_praktik' => '1,3,5', // Senin, Rabu, Jumat
            ],
            [
                'nama' => 'dr. Maya Kusuma, Sp.A',
                'spesialisasi' => 'Dokter Anak',
                'jam_mulai' => '09:00:00',
                'jam_selesai' => '13:00:00',
                'durasi_slot' => 20,
                'hari_praktik' => '2,4', // Selasa, Kamis
            ],
        ];

        foreach ($data as $d) {
            Dokter::updateOrCreate(['nama' => $d['nama']], $d);
        }
    }
}
