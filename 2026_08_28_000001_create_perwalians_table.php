<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('dosen_mahasiswa', function (Blueprint $table) { $table->id(); $table->foreignId('dosen_id')->constrained('users')->cascadeOnDelete(); $table->foreignId('mahasiswa_id')->unique()->constrained('users')->cascadeOnDelete(); $table->timestamps(); });
  Schema::create('perwalians', function (Blueprint $table) { $table->id(); $table->foreignId('mahasiswa_id')->constrained('users')->cascadeOnDelete(); $table->foreignId('dosen_id')->constrained('users')->cascadeOnDelete(); $table->date('tanggal'); $table->string('topik'); $table->text('hasil_konsultasi'); $table->text('rencana_tindak_lanjut')->nullable(); $table->enum('status', ['diajukan','ditinjau','selesai'])->default('diajukan'); $table->timestamps(); });
 }
 public function down(): void { Schema::dropIfExists('perwalians'); Schema::dropIfExists('dosen_mahasiswa'); }
};
