<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan posisi visual simpul agar layout yang diatur user (drag)
 * tetap tersimpan antar sesi. Nullable: bila kosong, auto-layout dagre
 * yang menentukan posisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->float('pos_x')->nullable()->after('order');
            $table->float('pos_y')->nullable()->after('pos_x');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y']);
        });
    }
};
