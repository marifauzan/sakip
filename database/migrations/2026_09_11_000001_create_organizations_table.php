<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->comment('kementerian_lembaga|pemerintah_daerah|opd');
            $table->string('code', 64)->nullable()->comment('kode instansi');
            $table->timestamps();
        });

        // Tambah kolom organisasi + peran pada users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')
                ->constrained('organizations')->nullOnDelete();
            $table->string('role', 32)->default('planner')
                ->after('organization_id')->comment('admin|planner|reviewer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('organizations');
    }
};
