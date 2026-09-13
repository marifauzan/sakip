<?php

namespace Tests\Feature;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReviewAndExportTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private KinerjaTree $tree;

    private Node $node;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Kementerian Pertanian',
            'type' => 'kementerian_lembaga',
            'code' => 'KEMENTAN',
        ]);

        $this->reviewer = User::create([
            'name' => 'Reviewer Kementan',
            'email' => 'reviewer@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $org->id,
            'role' => 'reviewer',
        ]);

        $this->tree = KinerjaTree::create([
            'organization_id' => $org->id,
            'name' => 'Renstra Kementan 2025-2029',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'draft',
        ]);

        $this->node = $this->tree->nodes()->create([
            'statement' => 'Meningkatnya Nilai Tambah dan Daya Saing Komoditas Pertanian',
            'type' => 'outcome',
            'source_type' => 'user_edited',
        ]);

        $this->node->indicators()->create([
            'name' => 'Nilai Ekspor Pertanian',
            'unit' => 'Triliun Rupiah',
            'direction' => 'naik',
            'baseline' => '500',
            'target' => '650',
        ]);
    }

    public function test_reviewer_can_add_comment_on_tree(): void
    {
        $response = $this->actingAs($this->reviewer)
            ->post("/kinerja/{$this->tree->id}/reviews", [
                'decision' => 'comment',
                'comment' => 'Mohon pertimbangkan faktor perubahan iklim pada target produksi.',
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', [
            'tree_id' => $this->tree->id,
            'user_id' => $this->reviewer->id,
            'node_id' => null,
            'decision' => 'comment',
            'comment' => 'Mohon pertimbangkan faktor perubahan iklim pada target produksi.',
        ]);
    }

    public function test_reviewer_approval_updates_node_source_type_to_approved(): void
    {
        $response = $this->actingAs($this->reviewer)
            ->post("/kinerja/{$this->tree->id}/reviews", [
                'node_id' => $this->node->id,
                'decision' => 'approve',
                'comment' => 'Rumusan sasaran sudah sesuai kaidah PermenPANRB 89/2021.',
            ]);

        $response->assertSessionHas('success');

        $this->node->refresh();
        $this->assertEquals('approved', $this->node->source_type);

        $this->assertDatabaseHas('reviews', [
            'tree_id' => $this->tree->id,
            'node_id' => $this->node->id,
            'decision' => 'approve',
        ]);
    }

    public function test_can_export_kinerja_tree_to_markdown(): void
    {
        $response = $this->actingAs($this->reviewer)
            ->get("/kinerja/{$this->tree->id}/export?format=markdown");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/markdown; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('# Renstra Kementan 2025-2029', $content);
        $this->assertStringContainsString('Meningkatnya Nilai Tambah dan Daya Saing Komoditas Pertanian', $content);
        $this->assertStringContainsString('Nilai Ekspor Pertanian', $content);
    }

    public function test_can_export_kinerja_tree_to_json(): void
    {
        $response = $this->actingAs($this->reviewer)
            ->get("/kinerja/{$this->tree->id}/export?format=json");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');

        $content = $response->streamedContent();
        $data = json_decode($content, true);

        $this->assertEquals('Renstra Kementan 2025-2029', $data['name']);
        $this->assertCount(1, $data['nodes']);
        $this->assertEquals('Meningkatnya Nilai Tambah dan Daya Saing Komoditas Pertanian', $data['nodes'][0]['statement']);
        $this->assertEquals('Nilai Ekspor Pertanian', $data['nodes'][0]['indicators'][0]['name']);
    }
}
