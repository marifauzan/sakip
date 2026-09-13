<?php

namespace Tests\Feature;

use App\Models\AiRecommendation;
use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\Organization;
use App\Models\User;
use App\Services\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AiRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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

        $this->user = User::create([
            'name' => 'Perencana Kementan',
            'email' => 'planner@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $org->id,
            'role' => 'planner',
        ]);

        $this->tree = KinerjaTree::create([
            'organization_id' => $org->id,
            'name' => 'Renstra Kementan',
            'status' => 'draft',
        ]);

        $this->node = $this->tree->nodes()->create([
            'statement' => 'Meningkatnya Produksi Pangan Strategis',
            'type' => 'outcome',
        ]);
    }

    public function test_ai_recommend_children_returns_recommendations_and_logs_audit(): void
    {
        $mockLlm = Mockery::mock(LlmClient::class);
        $mockLlm->shouldReceive('chat')
            ->once()
            ->andReturn([
                'recommendations' => [
                    [
                        'statement' => 'Tersedianya Benih Unggul Bersertifikat',
                        'relationship_reason' => 'Benih unggul menentukan produktivitas hasil panen.',
                        'assumptions' => ['Distribusi lancar'],
                        'missing_data' => [],
                    ],
                ],
            ]);

        $this->app->instance(LlmClient::class, $mockLlm);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'false')
            ->post("/kinerja/{$this->tree->id}/nodes/{$this->node->id}/ai/children");

        $response->assertStatus(200)
            ->assertJsonPath('recommendations.0.statement', 'Tersedianya Benih Unggul Bersertifikat');

        $this->assertDatabaseHas('ai_recommendations', [
            'organization_id' => $this->user->organization_id,
            'tree_id' => $this->tree->id,
            'node_id' => $this->node->id,
            'kind' => 'recommend_children',
        ]);
    }

    public function test_ai_recommend_indicators_returns_indicators_and_logs_audit(): void
    {
        $mockLlm = Mockery::mock(LlmClient::class);
        $mockLlm->shouldReceive('chat')
            ->once()
            ->andReturn([
                'indicators' => [
                    [
                        'name' => 'Persentase Penggunaan Benih Bersertifikat',
                        'definition' => 'Proporsi luas tanam yang memakai benih resmi',
                        'unit' => '%',
                        'direction' => 'naik',
                        'data_source' => 'Ditjen Tanaman Pangan',
                    ],
                ],
            ]);

        $this->app->instance(LlmClient::class, $mockLlm);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'false')
            ->post("/kinerja/{$this->tree->id}/nodes/{$this->node->id}/ai/indicators");

        $response->assertStatus(200)
            ->assertJsonPath('indicators.0.name', 'Persentase Penggunaan Benih Bersertifikat');

        $this->assertDatabaseHas('ai_recommendations', [
            'organization_id' => $this->user->organization_id,
            'tree_id' => $this->tree->id,
            'node_id' => $this->node->id,
            'kind' => 'recommend_indicators',
        ]);
    }

    public function test_accept_children_creates_node_and_link(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/nodes/{$this->node->id}/ai/children/accept", [
                'statement' => 'Tersedianya Sarana Prasarana Pertanian Modern',
                'relationship_reason' => 'Mendukung mekanisasi pengolahan lahan.',
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('nodes', [
            'tree_id' => $this->tree->id,
            'statement' => 'Tersedianya Sarana Prasarana Pertanian Modern',
            'source_type' => 'ai_proposed',
        ]);

        $child = Node::where('statement', 'Tersedianya Sarana Prasarana Pertanian Modern')->first();

        $this->assertDatabaseHas('node_links', [
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->node->id,
            'child_node_id' => $child->id,
            'reason' => 'Mendukung mekanisasi pengolahan lahan.',
        ]);
    }

    public function test_accept_indicator_creates_indicator(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/nodes/{$this->node->id}/ai/indicators/accept", [
                'name' => 'Indeks Mekanisasi Pertanian',
                'unit' => 'hp/ha',
                'direction' => 'naik',
                'definition' => 'Daya traktor per luas lahan tanam',
                'data_source' => 'Ditjen PSP',
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('indicators', [
            'node_id' => $this->node->id,
            'name' => 'Indeks Mekanisasi Pertanian',
            'unit' => 'hp/ha',
            'direction' => 'naik',
        ]);
    }

    public function test_kinerja_show_loads_pending_ai_recommendations(): void
    {
        $rec = AiRecommendation::create([
            'organization_id' => $this->user->organization_id,
            'tree_id' => $this->tree->id,
            'node_id' => $this->node->id,
            'kind' => AiRecommendation::KIND_INDICATORS,
            'output' => [
                'indicators' => [
                    [
                        'name' => 'Persentase Lahan Beririgasi Memadai',
                        'unit' => '%',
                        'direction' => 'naik',
                    ],
                ],
            ],
            'decision' => 'pending',
        ]);

        $this->actingAs($this->user)
            ->get("/kinerja/{$this->tree->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/Show')
                ->where('tree.nodes.0.pending_ai_indicators.0.name', 'Persentase Lahan Beririgasi Memadai')
                ->where('tree.nodes.0.pending_indicator_recommendation_id', $rec->id)
            );
    }

    public function test_accept_indicator_with_recommendation_id_updates_ai_recommendation(): void
    {
        $rec = AiRecommendation::create([
            'organization_id' => $this->user->organization_id,
            'tree_id' => $this->tree->id,
            'node_id' => $this->node->id,
            'kind' => AiRecommendation::KIND_INDICATORS,
            'output' => [
                'indicators' => [
                    [
                        'name' => 'Indikator A',
                        'unit' => '%',
                        'direction' => 'naik',
                    ],
                    [
                        'name' => 'Indikator B',
                        'unit' => 'unit',
                        'direction' => 'naik',
                    ],
                ],
            ],
            'decision' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/nodes/{$this->node->id}/ai/indicators/accept", [
                'name' => 'Indikator A',
                'unit' => '%',
                'direction' => 'naik',
                'recommendation_id' => $rec->id,
            ]);

        $response->assertSessionHas('success');

        $rec->refresh();
        $this->assertSame('pending', $rec->decision);
        $this->assertCount(1, $rec->output['indicators']);
        $this->assertSame('Indikator B', $rec->output['indicators'][0]['name']);

        // Accept last item -> marks as accepted
        $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/nodes/{$this->node->id}/ai/indicators/accept", [
                'name' => 'Indikator B',
                'unit' => 'unit',
                'direction' => 'naik',
                'recommendation_id' => $rec->id,
            ]);

        $rec->refresh();
        $this->assertSame('accepted', $rec->decision);
        $this->assertEmpty($rec->output['indicators']);
    }

    public function test_dismiss_recommendations_marks_pending_as_rejected(): void
    {
        $rec = AiRecommendation::create([
            'organization_id' => $this->user->organization_id,
            'tree_id' => $this->tree->id,
            'node_id' => $this->node->id,
            'kind' => AiRecommendation::KIND_INDICATORS,
            'output' => ['indicators' => [['name' => 'Indikator X']]],
            'decision' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/nodes/{$this->node->id}/ai/dismiss", [
                'kind' => AiRecommendation::KIND_INDICATORS,
            ]);

        $response->assertSessionHas('success');

        $rec->refresh();
        $this->assertSame('rejected', $rec->decision);
    }
}
