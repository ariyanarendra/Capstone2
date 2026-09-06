<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perwalians', function (Blueprint $table) {
            $table->text('catatan_dosen')->nullable()->after('rencana_tindak_lanjut');
        });
    }

    public function down(): void
    {
        Schema::table('perwalians', function (Blueprint $table) {
            $table->dropColumn('catatan_dosen');
        });
    }
};
