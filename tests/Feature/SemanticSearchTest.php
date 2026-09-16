<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Organization;
use App\Services\EmbeddingClient;
use App\Services\SemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Org A', 'type' => 'kementerian_lembaga']);
        $this->otherOrg = Organization::create(['name' => 'Org B', 'type' => 'pemerintah_daerah']);
    }

    private function makeDocument(Organization $org, string $title, string $content): Document
    {
        $doc = Document::create([
            'organization_id' => $org->id,
            'title' => $title,
            'type' => 'renstra',
            'status' => 'extracted',
            'file_path' => 'documents/x.pdf',
            'file_name' => 'x.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1,
        ]);

        DocumentChunk::create([
            'document_id' => $doc->id,
            'page' => 12,
            'section' => 'SASARAN',
            'chunk_index' => 0,
            'content' => $content,
        ]);

        return $doc;
    }

    public function test_search_falls_back_to_text_when_embedding_not_configured(): void
    {
        // Pastikan provider embedding kosong -> mode teks.
        config()->set('llm.embedding_model', '');

        $this->makeDocument($this->org, 'Renstra A', 'peningkatan produksi pangan strategis nasional');

        $results = app(SemanticSearchService::class)->search($this->org->id, 'produksi pangan', 5);

        $this->assertNotEmpty($results);
        $this->assertSame('text', $results[0]['mode']);
        $this->assertSame('Renstra A', $results[0]['document_title']);
        $this->assertSame(12, $results[0]['page']);
    }

    public function test_search_is_tenant_scoped(): void
    {
        config()->set('llm.embedding_model', '');

        $this->makeDocument($this->otherOrg, 'Renstra Org B', 'produksi pangan strategis');

        $results = app(SemanticSearchService::class)->search($this->org->id, 'produksi pangan', 5);

        $this->assertSame([], $results, 'Pencarian tidak boleh bocor lintas organisasi.');
    }

    public function test_search_requires_all_tokens_present(): void
    {
        config()->set('llm.embedding_model', '');

        $this->makeDocument($this->org, 'Renstra A', 'peningkatan produksi pangan');

        $results = app(SemanticSearchService::class)->search($this->org->id, 'pangan infrastruktur', 5);

        $this->assertSame([], $results, 'Query dengan token tak ada seharusnya kosong.');
    }

    public function test_reference_label_format(): void
    {
        $label = app(SemanticSearchService::class)->referenceLabel([
            'document_id' => 7, 'page' => 12,
        ]);

        $this->assertSame('D7-H12', $label);
    }

    public function test_embedding_client_reports_not_configured_without_model(): void
    {
        config()->set('llm.embedding_model', '');
        $this->assertFalse(app(EmbeddingClient::class)->isConfigured());
    }

    public function test_embedding_client_returns_empty_when_not_configured(): void
    {
        config()->set('llm.embedding_model', '');
        $this->assertSame([], app(EmbeddingClient::class)->embedBatch(['apa saja']));
    }

    public function test_vector_literal_format(): void
    {
        $literal = app(EmbeddingClient::class)->toVectorLiteral([0.1, 0.25, -1.0]);
        $this->assertSame('[0.1,0.25,-1]', $literal);
    }
}
