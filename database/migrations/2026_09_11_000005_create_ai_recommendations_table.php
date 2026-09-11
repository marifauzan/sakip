<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tree_id')->nullable()->constrained('kinerja_trees')->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('nodes')->cascadeOnDelete();
            $table->string('kind')->comment('recommend_children|recommend_indicators|detect_sector');
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_version')->default(1);
            $table->text('context_summary')->nullable()->comment('ringkasan konteks yang dikirim');
            $table->jsonb('output')->nullable();
            $table->string('decision')->default('pending')->comment('pending|accepted|rejected');
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['node_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
    }
};
