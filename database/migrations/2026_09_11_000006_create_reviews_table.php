<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained('kinerja_trees')->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('decision')->default('comment')->comment('comment|approve|reject');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('tree_id');
            $table->index('node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
