<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dokter extends Model
{
    protected $table = 'dokters';

    protected $fillable = [
        'nama', 'spesialisasi', 'jam_mulai', 'jam_selesai',
        'durasi_slot', 'hari_praktik', 'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'durasi_slot' => 'integer',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'dokter_id');
    }

    /** Daftar nomor hari praktik, contoh: [1,2,3,4,5] */
    public function hariPraktikList(): array
    {
        return array_map('intval', array_filter(explode(',', (string) $this->hari_praktik)));
    }

    public function praktikPada(int $nomorHari): bool
    {
        return in_array($nomorHari, $this->hariPraktikList(), true);
    }
}
