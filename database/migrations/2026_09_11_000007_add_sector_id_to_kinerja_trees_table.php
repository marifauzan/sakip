<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kinerja_trees', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->after('organization_id')->constrained('sectors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kinerja_trees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
        });
    }
};
