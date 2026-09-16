<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

/**
 * Pencarian potongan dokumen (hybrid retrieval).
 *
 * Strategi:
 *  1. Jika embedding tersedia  -> pencarian semantik (pgvector, cosine).
 *  2. Jika tidak/gagal         -> fallback pencarian teks (LIKE/ILIKE).
 *
 * Hasil selalu berupa array chunk + metadata (dokumen, halaman, bagian)
 * agar dapat dikutip sebagai sumber.
 */
class SemanticSearchService
{
    public function __construct(private EmbeddingClient $embeddings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(int $organizationId, string $query, int $limit = 5): array
    {
        if ($this->embeddings->isConfigured() && $this->hasEmbeddings($organizationId)) {
            $semantic = $this->semanticSearch($organizationId, $query, $limit);
            if ($semantic !== []) {
                return $semantic;
            }
        }

        return $this->textSearch($organizationId, $query, $limit);
    }

    /** Apakah ada minimal satu chunk ber-embedding untuk organisasi ini? */
    private function hasEmbeddings(int $organizationId): bool
    {
        return DocumentChunk::query()
            ->join('documents', 'documents.id', '=', 'document_chunks.document_id')
            ->where('documents.organization_id', $organizationId)
            ->whereNotNull('document_chunks.embedding')
            ->exists();
    }

    /**
     * Pencarian semantik via pgvector (cosine distance).
     *
     * @return array<int, array<string, mixed>>
     */
    private function semanticSearch(int $organizationId, string $query, int $limit): array
    {
        $queryEmbedding = $this->embeddings->embed($query);

        if ($queryEmbedding === null || $queryEmbedding === []) {
            return [];
        }

        $vector = $this->embeddings->toVectorLiteral($queryEmbedding);

        // Join dokumen untuk filter tenant + ambil metadata sitasi.
        $rows = DB::select(
            'SELECT dc.id, dc.page, dc.section, dc.chunk_index, dc.content,
                    d.title AS document_title, d.id AS document_id,
                    (dc.embedding <=> ?::vector) AS distance
             FROM document_chunks dc
             JOIN documents d ON d.id = dc.document_id
             WHERE d.organization_id = ?
               AND d.status = \'extracted\'
               AND dc.embedding IS NOT NULL
             ORDER BY dc.embedding <=> ?::vector
             LIMIT ?',
            [$vector, $organizationId, $vector, $limit]
        );

        return array_map(fn ($r) => [
            'document_title' => $r->document_title,
            'document_id' => (int) $r->document_id,
            'page' => $r->page,
            'section' => $r->section,
            'chunk_index' => $r->chunk_index,
            'content' => $r->content,
            'score' => 1 - (float) $r->distance,
            'mode' => 'semantic',
        ], $rows);
    }

    /**
     * Fallback: pencarian teks (semua kata kunci harus muncul).
     *
     * @return array<int, array<string, mixed>>
     */
    private function textSearch(int $organizationId, string $query, int $limit): array
    {
        $tokens = array_filter(
            preg_split('/\s+/u', mb_strtolower($query)) ?: [],
            fn ($t) => mb_strlen($t) > 3
        );

        if ($tokens === []) {
            return [];
        }

        $results = [];
        $chunks = DocumentChunk::query()
            ->with('document')
            ->whereHas('document', fn ($q) => $q
                ->where('organization_id', $organizationId)
                ->where('status', 'extracted'))
            ->get();

        foreach ($chunks as $chunk) {
            $matched = true;
            foreach ($tokens as $token) {
                if (mb_stripos($chunk->content, $token) === false) {
                    $matched = false;
                    break;
                }
            }
            if (! $matched) {
                continue;
            }

            $results[] = [
                'document_title' => $chunk->document?->title,
                'document_id' => $chunk->document_id,
                'page' => $chunk->page,
                'section' => $chunk->section,
                'chunk_index' => $chunk->chunk_index,
                'content' => $chunk->content,
                'score' => null,
                'mode' => 'text',
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /** Label rujukan yang bisa dikutip AI, mis. "D7-H12". */
    public function referenceLabel(array $chunk): string
    {
        return 'D'.$chunk['document_id'].'-H'.($chunk['page'] ?? '?');
    }
}
