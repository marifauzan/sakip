<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('renstra')
                ->comment('renstra|rpjmd|rpjmn|renstra_opd|lainnya');
            $table->unsignedSmallInteger('period_start')->nullable();
            $table->unsignedSmallInteger('period_end')->nullable();
            $table->string('status')->default('uploaded')
                ->comment('uploaded|extracting|extracted|failed');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sector_confirmed_at')->nullable();
            $table->text('extract_error')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page')->nullable();
            $table->string('section')->nullable();
            $table->text('content');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->timestamps();

            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
