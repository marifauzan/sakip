<?php

namespace Tests\Feature;

use App\Jobs\ExtractDocument;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Services\DocumentExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Dinas Pendidikan',
            'type' => 'pemerintah_daerah',
            'code' => 'DISDIK',
        ]);

        $this->user = User::create([
            'name' => 'Planner Disdik',
            'email' => 'planner@disdik.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org->id,
            'role' => 'planner',
        ]);
    }

    public function test_document_index_renders_successfully(): void
    {
        $this->actingAs($this->user)
            ->get('/documents')
            ->assertStatus(200);
    }

    public function test_document_upload_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post('/documents', [])
            ->assertSessionHasErrors(['title', 'type', 'file']);
    }

    public function test_document_can_be_uploaded_and_extraction_dispatched(): void
    {
        Queue::fake();
        Storage::fake('private');

        $file = UploadedFile::fake()->create('renstra.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->post('/documents', [
            'title' => 'Renstra Disdik 2025-2029',
            'type' => 'renstra',
            'period_start' => 2025,
            'period_end' => 2029,
            'file' => $file,
        ]);

        $response->assertRedirect('/documents');

        $this->assertDatabaseHas('documents', [
            'organization_id' => $this->org->id,
            'title' => 'Renstra Disdik 2025-2029',
            'type' => 'renstra',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'uploaded',
            'file_name' => 'renstra.pdf',
        ]);

        Queue::assertPushed(ExtractDocument::class);
    }

    public function test_document_extractor_creates_chunks_from_text_file(): void
    {
        Storage::fake('private');

        $content = "BAB I PENDAHULUAN\nRenstra Dinas Pendidikan adalah pedoman kerja.\nMeningkatkan mutu layanan pendidikan dasar dan menengah.\n\nBAB II TUJUAN DAN SASARAN\nMewujudkan akses pendidikan yang merata dan bermutu tinggi bagi seluruh masyarakat.";
        $relativePath = 'documents/sample.txt';
        Storage::disk('private')->put($relativePath, $content);

        $document = Document::create([
            'organization_id' => $this->org->id,
            'title' => 'Sample Text Document',
            'type' => 'renstra',
            'file_path' => $relativePath,
            'file_name' => 'sample.txt',
            'file_mime' => 'text/plain',
            'file_size' => strlen($content),
            'status' => 'uploaded',
        ]);

        $extractor = new DocumentExtractor;
        $extractor->extract($document);

        $document->refresh();
        $this->assertEquals('extracted', $document->status);
        $this->assertNull($document->extract_error);
        $this->assertGreaterThan(0, $document->chunks()->count());

        $firstChunk = $document->chunks()->first();
        $this->assertStringContainsString('BAB I PENDAHULUAN', $firstChunk->content);
    }

    public function test_document_upload_accepts_file_up_to_50mb(): void
    {
        Queue::fake();
        Storage::fake('private');

        // 25 MB file (25600 KB) - well within 50 MB (51200 KB)
        $file = UploadedFile::fake()->create('renstra_besar.pdf', 25600, 'application/pdf');

        $response = $this->actingAs($this->user)->post('/documents', [
            'title' => 'Renstra Dokumen Besar',
            'type' => 'renstra',
            'period_start' => 2025,
            'period_end' => 2029,
            'file' => $file,
        ]);

        $response->assertRedirect('/documents');
        $this->assertDatabaseHas('documents', [
            'title' => 'Renstra Dokumen Besar',
            'file_name' => 'renstra_besar.pdf',
        ]);
    }

    public function test_document_upload_rejects_file_exceeding_50mb_with_indonesian_message(): void
    {
        Queue::fake();
        Storage::fake('private');

        // 55 MB file (56320 KB)
        $file = UploadedFile::fake()->create('renstra_terlalu_besar.pdf', 56320, 'application/pdf');

        $response = $this->actingAs($this->user)->post('/documents', [
            'title' => 'Renstra Terlalu Besar',
            'type' => 'renstra',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors([
            'file' => 'Ukuran file tidak boleh melebihi 50 MB.',
        ]);
    }

    public function test_document_extractor_handles_docx_with_zip_mime_type(): void
    {
        Storage::fake('private');

        $tempPath = tempnam(sys_get_temp_dir(), 'docx_test');
        $zip = new \ZipArchive;
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:r><w:t>BAB I PENDAHULUAN RENSTRA</w:t></w:r></w:p>
        <w:p><w:r><w:t>Target capaian pendidikan meningkat 15 persen.</w:t></w:r></w:p>
    </w:body>
</w:document>';
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        $docxContent = file_get_contents($tempPath);
        unlink($tempPath);

        $relativePath = 'documents/sample.docx';
        Storage::disk('private')->put($relativePath, $docxContent);

        $document = Document::create([
            'organization_id' => $this->org->id,
            'title' => 'Sample DOCX File',
            'type' => 'renstra',
            'file_path' => $relativePath,
            'file_name' => 'sample.docx',
            'file_mime' => 'application/zip', // common mime detected by finfo
            'file_size' => strlen($docxContent),
            'status' => 'uploaded',
        ]);

        $extractor = new DocumentExtractor;
        $extractor->extract($document);

        $document->refresh();
        $this->assertEquals('extracted', $document->status);
        $this->assertNull($document->extract_error);
        $this->assertGreaterThan(0, $document->chunks()->count());
        $this->assertStringContainsString('BAB I PENDAHULUAN RENSTRA', $document->chunks()->first()->content);
    }

    public function test_document_extractor_handles_legacy_binary_doc_with_friendly_message(): void
    {
        Storage::fake('private');

        $binaryContent = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1 Legacy Word Document";
        $relativePath = 'documents/old.doc';
        Storage::disk('private')->put($relativePath, $binaryContent);

        $document = Document::create([
            'organization_id' => $this->org->id,
            'title' => 'Old Word Document',
            'type' => 'renstra',
            'file_path' => $relativePath,
            'file_name' => 'old.doc',
            'file_mime' => 'application/msword',
            'file_size' => strlen($binaryContent),
            'status' => 'uploaded',
        ]);

        $extractor = new DocumentExtractor;

        try {
            $extractor->extract($document);
        } catch (\Throwable $e) {
            // expected to throw for background worker retry/fail logic
        }

        $document->refresh();
        $this->assertEquals('failed', $document->status);
        $this->assertStringContainsString('Word 97-2003', $document->extract_error);
    }

    public function test_retry_extract_re_dispatches_job(): void
    {
        Queue::fake();

        $document = Document::create([
            'organization_id' => $this->org->id,
            'title' => 'Dokumen Gagal',
            'type' => 'renstra',
            'file_path' => 'documents/gagal.pdf',
            'file_name' => 'gagal.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1024,
            'status' => 'failed',
            'extract_error' => 'Gagal membaca PDF',
        ]);

        $response = $this->actingAs($this->user)->post("/documents/{$document->id}/retry-extract");

        $response->assertSessionHas('success');
        $document->refresh();
        $this->assertEquals('uploaded', $document->status);
        $this->assertNull($document->extract_error);

        Queue::assertPushed(ExtractDocument::class);
    }

    public function test_user_cannot_retry_extract_other_organization_document(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Kemenag',
            'type' => 'kementerian',
            'code' => 'KEMENAG',
        ]);

        $otherDocument = Document::create([
            'organization_id' => $otherOrg->id,
            'title' => 'Dokumen Kemenag',
            'type' => 'renstra',
            'file_path' => 'documents/kemenag.pdf',
            'file_name' => 'kemenag.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1024,
            'status' => 'failed',
            'extract_error' => 'Error',
        ]);

        $response = $this->actingAs($this->user)->post("/documents/{$otherDocument->id}/retry-extract");
        $response->assertStatus(403);
    }
}
