<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom embedding vector ke document_chunks (pgvector).
 *
 * Catatan: migrasi ini HANYA berjalan di PostgreSQL yang punya ekstensi
 * `vector`. Di driver lain (mis. SQLite untuk test) migrasi dilewati agar
 * test suite tetap jalan tanpa pgvector.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // 1536 dimensi = kompatibel dengan model embedding umum
        // (mis. text-embedding-3-small). Kolom nullable: chunk tanpa
        // embedding tetap bisa dipakai via pencarian teks (fallback).
        DB::statement('ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedding vector(1536)');

        // Index HNSW untuk pencarian cosine distance yang cepat.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS document_chunks_embedding_idx
             ON document_chunks USING hnsw (embedding vector_cosine_ops)'
        );

        DB::statement('ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedding_model varchar(64)');
        DB::statement('ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedded_at timestamp');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding_model');
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedded_at');
    }
};
