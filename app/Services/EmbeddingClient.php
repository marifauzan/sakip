<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien embedding (OpenAI-compatible /embeddings).
 *
 * Dirancang graceful: jika provider embedding tidak dikonfigurasi atau
 * gagal, method mengembalikan null sehingga pemanggil bisa fallback ke
 * pencarian teks biasa (aplikasi tetap berfungsi tanpa embedding).
 */
class EmbeddingClient
{
    private const DIMENSIONS = 1536;

    public function isConfigured(): bool
    {
        return ! empty(config('llm.embedding_model')) && ! empty(config('llm.api_key'));
    }

    /**
     * Hitung embedding untuk satu teks.
     *
     * @return array<int, float>|null  null jika tidak tersedia.
     */
    public function embed(string $text): ?array
    {
        $results = $this->embedBatch([$text]);

        return $results[0] ?? null;
    }

    /**
     * Hitung embedding untuk banyak teks sekaligus (lebih efisien).
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>  array bisa kosong jika gagal.
     */
    public function embedBatch(array $texts): array
    {
        if (! $this->isConfigured() || $texts === []) {
            return [];
        }

        try {
            $response = Http::baseUrl(config('llm.base_url'))
                ->withToken(config('llm.api_key'))
                ->timeout(config('llm.timeout'))
                ->retry(2, 500)
                ->post('/embeddings', [
                    'model' => config('llm.embedding_model'),
                    'input' => array_values($texts),
                ]);

            if ($response->failed()) {
                Log::warning('Embedding gagal', ['status' => $response->status()]);

                return [];
            }

            $data = $response->json('data', []);

            // Urutkan berdasarkan index agar sesuai urutan input.
            usort($data, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

            return array_map(fn ($row) => $row['embedding'] ?? [], $data);
        } catch (\Throwable $e) {
            Log::warning('Embedding exception', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /** Format vector untuk literal pgvector: '[0.1,0.2,...]'. */
    public function toVectorLiteral(array $embedding): string
    {
        return '['.implode(',', array_map(fn ($v) => (float) $v, $embedding)).']';
    }

    public function expectedDimensions(): int
    {
        return self::DIMENSIONS;
    }
}
