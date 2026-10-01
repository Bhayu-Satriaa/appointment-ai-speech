<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $table = 'appointments';

    protected $fillable = [
        'kode_booking', 'dokter_id', 'nama_pasien', 'telepon',
        'tanggal', 'jam', 'keluhan', 'status', 'sumber',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'dokter_id');
    }
}
