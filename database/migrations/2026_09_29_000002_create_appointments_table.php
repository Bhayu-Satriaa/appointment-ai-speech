<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('kode_booking', 12)->unique();
            $table->foreignId('dokter_id')->constrained('dokters')->cascadeOnDelete();
            $table->string('nama_pasien');
            $table->string('telepon', 20)->nullable();
            $table->date('tanggal');
            $table->time('jam');
            $table->text('keluhan')->nullable();
            $table->string('status', 20)->default('terkonfirmasi');
            $table->string('sumber', 10)->default('chat')->comment('chat | suara');
            $table->timestamps();

            // Kunci utama anti double-booking: satu dokter tidak bisa punya
            // dua janji di tanggal & jam yang sama.
            $table->unique(['dokter_id', 'tanggal', 'jam'], 'unik_slot_dokter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
