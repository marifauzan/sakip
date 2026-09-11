<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kinerja_trees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('period_start')->nullable();
            $table->unsignedSmallInteger('period_end')->nullable();
            $table->string('status')->default('draft')->comment('draft|published');
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained('kinerja_trees')->cascadeOnDelete();
            $table->string('code', 64)->nullable();
            $table->text('statement');
            $table->string('type')->default('outcome')->comment('outcome|output|aktivitas');
            $table->string('source_type')->default('user_edited')
                ->comment('extracted|ai_proposed|user_edited|approved');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index('tree_id');
        });

        Schema::create('node_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained('kinerja_trees')->cascadeOnDelete();
            $table->foreignId('parent_node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('child_node_id')->constrained('nodes')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['tree_id', 'parent_node_id', 'child_node_id']);
            $table->index('tree_id');
        });

        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('definition')->nullable();
            $table->string('unit')->nullable();
            $table->string('direction')->default('naik')->comment('naik|turun|tetap');
            $table->string('data_source')->nullable();
            $table->string('baseline')->nullable();
            $table->string('target')->nullable();
            $table->timestamps();

            $table->index('node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('node_links');
        Schema::dropIfExists('nodes');
        Schema::dropIfExists('kinerja_trees');
    }
};
