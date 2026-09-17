<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use App\Models\Organization;
use App\Models\User;
use App\Services\KinerjaExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportFormatTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private User $user;
    private User $outsider;
    private KinerjaTree $tree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Kementan', 'type' => 'kementerian_lembaga']);
        $this->otherOrg = Organization::create(['name' => 'Pemda', 'type' => 'pemerintah_daerah']);

        $this->user = User::create([
            'name' => 'Planner', 'email' => 'p@test.test', 'password' => bcrypt('x'),
            'organization_id' => $this->org->id, 'role' => 'planner',
        ]);
        $this->outsider = User::create([
            'name' => 'Outsider', 'email' => 'o@test.test', 'password' => bcrypt('x'),
            'organization_id' => $this->otherOrg->id, 'role' => 'planner',
        ]);

        $this->tree = KinerjaTree::create([
            'organization_id' => $this->org->id,
            'name' => 'Perjenjangan Kinerja 2026',
            'period_start' => 2026,
            'period_end' => 2026,
            'status' => 'draft',
        ]);

        $parent = $this->tree->nodes()->create([
            'code' => 'SS.1',
            'statement' => 'Meningkatnya produksi pangan strategis',
            'type' => 'outcome',
            'source_type' => 'user_edited',
        ]);
        $child = $this->tree->nodes()->create([
            'code' => 'SS.1.1',
            'statement' => 'Meningkatnya produktivitas lahan',
            'type' => 'outcome',
            'source_type' => 'ai_proposed',
        ]);

        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $parent->id,
            'child_node_id' => $child->id,
            'reason' => 'Produktivitas mendukung produksi',
        ]);

        Indicator::create([
            'node_id' => $parent->id,
            'name' => 'Produksi padi',
            'unit' => 'juta ton',
            'direction' => 'naik',
            'baseline' => '3.2',
            'target' => '3.8',
        ]);
    }

    // ---------- DOCX ----------

    public function test_docx_export_returns_valid_zip(): void
    {
        $content = app(KinerjaExporter::class)->toDocx($this->tree);

        $this->assertSame('PK', substr($content, 0, 2), 'DOCX harus berupa ZIP (magic bytes PK).');
        $this->assertGreaterThan(500, strlen($content));
    }

    public function test_docx_contains_document_xml_with_hierarchy_and_table(): void
    {
        $content = app(KinerjaExporter::class)->toDocx($this->tree);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_test_');
        file_put_contents($tmp, $content);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $xml = $zip->getFromName('word/document.xml');
        $hasContentTypes = $zip->getFromName('[Content_Types].xml') !== false;
        $zip->close();
        @unlink($tmp);

        $this->assertNotFalse($xml);
        $this->assertTrue($hasContentTypes, 'DOCX harus punya [Content_Types].xml.');
        $this->assertStringContainsString('Meningkatnya produksi pangan strategis', $xml);
        $this->assertStringContainsString('Meningkatnya produktivitas lahan', $xml);
        $this->assertStringContainsString('<w:tbl>', $xml, 'Tabel indikator harus ada.');
        $this->assertStringContainsString('Produksi padi', $xml);
    }

    public function test_docx_escapes_special_xml_characters(): void
    {
        $node = $this->tree->nodes()->create([
            'statement' => 'Sasaran dengan <tag> & "kutip"',
            'type' => 'outcome',
            'source_type' => 'user_edited',
        ]);

        $content = app(KinerjaExporter::class)->toDocx($this->tree);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_esc_');
        file_put_contents($tmp, $content);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        // Karakter harus di-escape agar XML valid (tidak boleh <tag> mentah).
        $this->assertStringNotContainsString('<tag>', $xml);
        $this->assertStringContainsString('&lt;tag&gt;', $xml);
        $this->assertStringContainsString('&amp;', $xml);
    }

    // ---------- PDF / HTML ----------

    public function test_html_export_contains_hierarchy_and_indicator_table(): void
    {
        $html = app(KinerjaExporter::class)->toHtml($this->tree);

        $this->assertStringContainsString('Meningkatnya produksi pangan strategis', $html);
        $this->assertStringContainsString('Meningkatnya produktivitas lahan', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('Produksi padi', $html);
    }

    public function test_html_escapes_user_input(): void
    {
        $this->tree->nodes()->create([
            'statement' => 'Sasaran <script>alert(1)</script>',
            'type' => 'outcome',
            'source_type' => 'user_edited',
        ]);

        $html = app(KinerjaExporter::class)->toHtml($this->tree);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_pdf_export_returns_valid_pdf(): void
    {
        $content = app(KinerjaExporter::class)->toPdf($this->tree);

        $this->assertStringStartsWith('%PDF', $content, 'PDF harus diawali header %PDF.');
        $this->assertGreaterThan(1000, strlen($content));
    }

    // ---------- HTTP + otorisasi ----------

    public function test_docx_endpoint_downloads_with_correct_content_type(): void
    {
        $res = $this->actingAs($this->user)
            ->get("/kinerja/{$this->tree->id}/export?format=docx");

        $res->assertOk();
        $this->assertStringContainsString(
            'wordprocessingml.document',
            (string) $res->headers->get('content-type')
        );
    }

    public function test_pdf_endpoint_downloads_with_correct_content_type(): void
    {
        $res = $this->actingAs($this->user)
            ->get("/kinerja/{$this->tree->id}/export?format=pdf");

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('content-type'));
    }

    public function test_outsider_cannot_export_other_organization_tree(): void
    {
        $this->actingAs($this->outsider)
            ->get("/kinerja/{$this->tree->id}/export?format=docx")
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->get("/kinerja/{$this->tree->id}/export?format=pdf")
            ->assertForbidden();
    }
}
