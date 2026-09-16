<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\EmbeddingClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Hitung & simpan embedding untuk seluruh chunk sebuah dokumen.
 *
 * Job ini "no-op" secara aman bila provider embedding tidak dikonfigurasi
 * (aplikasi tetap berfungsi dengan pencarian teks).
 */
class EmbedDocumentChunks implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public Document $document) {}

    public function handle(EmbeddingClient $embeddings): void
    {
        if ($embeddings->isConfigured() === false) {
            return; // tidak ada provider embedding -> lewati dengan tenang
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // pgvector hanya di PostgreSQL
        }

        // Proses bertahap (batch) agar hemat panggilan API.
        $this->document->chunks()
            ->whereNull('embedding')
            ->orderBy('chunk_index')
            ->chunk(32, function ($chunks) use ($embeddings) {
                $texts = $chunks->pluck('content')->all();
                $vectors = $embeddings->embedBatch($texts);

                if (count($vectors) !== count($texts)) {
                    return; // gagal -> hentikan batch ini, jangan simpan data keliru
                }

                foreach ($chunks as $i => $chunk) {
                    if (empty($vectors[$i])) {
                        continue;
                    }

                    DB::table('document_chunks')
                        ->where('id', $chunk->id)
                        ->update([
                            'embedding' => DB::raw("'".$embeddings->toVectorLiteral($vectors[$i])."'::vector"),
                            'embedding_model' => config('llm.embedding_model'),
                            'embedded_at' => now(),
                        ]);
                }
            });
    }
}
