<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(public Document $document) {}

    public function handle(DocumentExtractor $extractor): void
    {
        $extractor->extract($this->document);
    }
}
