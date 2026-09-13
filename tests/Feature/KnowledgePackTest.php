<?php

namespace Tests\Feature;

use App\Models\KinerjaTree;
use App\Models\KnowledgePack;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use App\Services\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class KnowledgePackTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org1;

    private Organization $org2;

    private User $plannerOrg1;

    private User $adminOrg1;

    private User $reviewerOrg1;

    private User $plannerOrg2;

    private Sector $globalSector;

    private Sector $org1Sector;

    private Sector $org2Sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org1 = Organization::create([
            'name' => 'Kementerian Pertanian',
            'type' => 'kementerian_lembaga',
            'code' => 'KEMENTAN',
        ]);

        $this->org2 = Organization::create([
            'name' => 'Kementerian Agama',
            'type' => 'kementerian_lembaga',
            'code' => 'KEMENAG',
        ]);

        $this->plannerOrg1 = User::create([
            'name' => 'Planner Kementan',
            'email' => 'planner@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org1->id,
            'role' => 'planner',
        ]);

        $this->adminOrg1 = User::create([
            'name' => 'Admin Kementan',
            'email' => 'admin@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org1->id,
            'role' => 'admin',
        ]);

        $this->reviewerOrg1 = User::create([
            'name' => 'Reviewer Kementan',
            'email' => 'reviewer@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org1->id,
            'role' => 'reviewer',
        ]);

        $this->plannerOrg2 = User::create([
            'name' => 'Planner Kemenag',
            'email' => 'planner@kemenag.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org2->id,
            'role' => 'planner',
        ]);

        $this->globalSector = Sector::create([
            'organization_id' => null,
            'name' => 'Pendidikan Nasional',
            'slug' => 'pendidikan-nasional',
            'description' => 'Sektor global pendidikan',
        ]);

        $this->org1Sector = Sector::create([
            'organization_id' => $this->org1->id,
            'name' => 'Pertanian Berkelanjutan',
            'slug' => 'pertanian-berkelanjutan',
            'description' => 'Sektor pertanian internal Kementan',
        ]);

        $this->org2Sector = Sector::create([
            'organization_id' => $this->org2->id,
            'name' => 'Bimbingan Masyarakat',
            'slug' => 'bimbingan-masyarakat',
            'description' => 'Sektor internal Kemenag',
        ]);
    }

    public function test_index_displays_global_and_organization_sectors_only(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->get('/knowledge-packs')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('KnowledgePack/Index')
                ->has('sectors', 2)
                ->where('sectors.0.id', $this->globalSector->id)
                ->where('sectors.1.id', $this->org1Sector->id)
                ->where('can_manage', true)
                ->where('user_role', 'planner')
            );
    }

    public function test_reviewer_can_view_index_with_read_only_status(): void
    {
        $this->actingAs($this->reviewerOrg1)
            ->get('/knowledge-packs')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('KnowledgePack/Index')
                ->where('can_manage', false)
                ->where('user_role', 'reviewer')
            );
    }

    public function test_planner_can_create_sector_for_own_organization(): void
    {
        $response = $this->actingAs($this->plannerOrg1)
            ->post('/knowledge-packs/sectors', [
                'name' => 'Peternakan dan Kesehatan Hewan',
                'description' => 'Urusan peternakan terintegrasi',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sectors', [
            'organization_id' => $this->org1->id,
            'name' => 'Peternakan dan Kesehatan Hewan',
            'slug' => 'peternakan-dan-kesehatan-hewan',
            'description' => 'Urusan peternakan terintegrasi',
        ]);
    }

    public function test_sector_creation_validates_unique_name_per_organization(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->post('/knowledge-packs/sectors', [
                'name' => 'Pertanian Berkelanjutan', // already exists in org1
            ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_planner_can_update_own_sector(): void
    {
        $response = $this->actingAs($this->plannerOrg1)
            ->put("/knowledge-packs/sectors/{$this->org1Sector->id}", [
                'name' => 'Pertanian Presisi Modern',
                'slug' => 'pertanian-presisi-modern',
                'description' => 'Deskripsi diperbarui',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sectors', [
            'id' => $this->org1Sector->id,
            'name' => 'Pertanian Presisi Modern',
            'slug' => 'pertanian-presisi-modern',
            'description' => 'Deskripsi diperbarui',
        ]);
    }

    public function test_user_cannot_update_global_sector(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->put("/knowledge-packs/sectors/{$this->globalSector->id}", [
                'name' => 'Pendidikan Diubah',
            ])
            ->assertStatus(403);
    }

    public function test_user_cannot_update_other_org_sector(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->put("/knowledge-packs/sectors/{$this->org2Sector->id}", [
                'name' => 'Masyarakat Diubah',
            ])
            ->assertStatus(403);
    }

    public function test_planner_can_delete_own_sector(): void
    {
        $response = $this->actingAs($this->plannerOrg1)
            ->delete("/knowledge-packs/sectors/{$this->org1Sector->id}");

        $response->assertRedirect(route('knowledge-packs.index'));
        $this->assertDatabaseMissing('sectors', ['id' => $this->org1Sector->id]);
    }

    public function test_user_cannot_delete_global_or_other_org_sector(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->delete("/knowledge-packs/sectors/{$this->globalSector->id}")
            ->assertStatus(403);

        $this->actingAs($this->plannerOrg1)
            ->delete("/knowledge-packs/sectors/{$this->org2Sector->id}")
            ->assertStatus(403);
    }

    public function test_reviewer_cannot_create_or_delete_sector(): void
    {
        $this->actingAs($this->reviewerOrg1)
            ->post('/knowledge-packs/sectors', [
                'name' => 'Sektor Ilegal',
            ])
            ->assertStatus(403);

        $this->actingAs($this->reviewerOrg1)
            ->delete("/knowledge-packs/sectors/{$this->org1Sector->id}")
            ->assertStatus(403);
    }

    public function test_planner_can_create_update_delete_and_toggle_knowledge_pack(): void
    {
        // 1. Create
        $createResponse = $this->actingAs($this->plannerOrg1)
            ->post("/knowledge-packs/sectors/{$this->org1Sector->id}/packs", [
                'title' => 'Dimensi Hasil Pangan',
                'source' => 'Renstra Kementan 2025-2029',
                'version' => 1,
                'is_active' => true,
                'content' => "### Dimensi Hasil\n- Akses Pangan\n- Mutu Pangan",
            ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('knowledge_packs', [
            'sector_id' => $this->org1Sector->id,
            'title' => 'Dimensi Hasil Pangan',
            'is_active' => true,
            'version' => 1,
        ]);

        $pack = KnowledgePack::where('title', 'Dimensi Hasil Pangan')->first();

        // 2. Update
        $updateResponse = $this->actingAs($this->plannerOrg1)
            ->put("/knowledge-packs/packs/{$pack->id}", [
                'title' => 'Dimensi Hasil Pangan Rev 2',
                'source' => 'Renstra Revisi',
                'version' => 2,
                'is_active' => true,
                'content' => '### Dimensi Hasil Revisi',
            ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('knowledge_packs', [
            'id' => $pack->id,
            'title' => 'Dimensi Hasil Pangan Rev 2',
            'version' => 2,
        ]);

        // 3. Toggle
        $this->actingAs($this->plannerOrg1)
            ->patch("/knowledge-packs/packs/{$pack->id}/toggle")
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_packs', [
            'id' => $pack->id,
            'is_active' => false,
        ]);

        // 4. Delete
        $deleteResponse = $this->actingAs($this->plannerOrg1)
            ->delete("/knowledge-packs/packs/{$pack->id}");

        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('knowledge_packs', ['id' => $pack->id]);
    }

    public function test_user_cannot_create_or_modify_pack_on_global_sector(): void
    {
        $globalPack = KnowledgePack::create([
            'sector_id' => $this->globalSector->id,
            'title' => 'Dimensi Pendidikan Global',
            'content' => 'Konten global',
            'version' => 1,
            'is_active' => true,
        ]);

        // Create on global sector
        $this->actingAs($this->plannerOrg1)
            ->post("/knowledge-packs/sectors/{$this->globalSector->id}/packs", [
                'title' => 'Pack Baru Global',
                'content' => 'Gagal',
            ])
            ->assertStatus(403);

        // Update pack in global sector
        $this->actingAs($this->plannerOrg1)
            ->put("/knowledge-packs/packs/{$globalPack->id}", [
                'title' => 'Diubah',
                'content' => 'Diubah',
            ])
            ->assertStatus(403);

        // Toggle pack in global sector
        $this->actingAs($this->plannerOrg1)
            ->patch("/knowledge-packs/packs/{$globalPack->id}/toggle")
            ->assertStatus(403);

        // Delete pack in global sector
        $this->actingAs($this->plannerOrg1)
            ->delete("/knowledge-packs/packs/{$globalPack->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_modify_other_org_knowledge_pack(): void
    {
        $org2Pack = KnowledgePack::create([
            'sector_id' => $this->org2Sector->id,
            'title' => 'Pack Org2',
            'content' => 'Konten Org2',
            'version' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->plannerOrg1)
            ->put("/knowledge-packs/packs/{$org2Pack->id}", [
                'title' => 'Hack title',
                'content' => 'Hack content',
            ])
            ->assertStatus(403);

        $this->actingAs($this->plannerOrg1)
            ->patch("/knowledge-packs/packs/{$org2Pack->id}/toggle")
            ->assertStatus(403);

        $this->actingAs($this->plannerOrg1)
            ->delete("/knowledge-packs/packs/{$org2Pack->id}")
            ->assertStatus(403);
    }

    public function test_reviewer_cannot_modify_knowledge_pack(): void
    {
        $pack = KnowledgePack::create([
            'sector_id' => $this->org1Sector->id,
            'title' => 'Pack Org1',
            'content' => 'Konten Org1',
            'version' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->reviewerOrg1)
            ->patch("/knowledge-packs/packs/{$pack->id}/toggle")
            ->assertStatus(403);
    }

    public function test_kinerja_tree_creation_with_sector_and_details_display(): void
    {
        // 1. Can create with sector_id
        $response = $this->actingAs($this->plannerOrg1)
            ->post('/kinerja', [
                'name' => 'Pohon Kinerja Pertanian 2026',
                'period_start' => 2026,
                'period_end' => 2030,
                'sector_id' => $this->org1Sector->id,
            ]);

        $this->assertDatabaseHas('kinerja_trees', [
            'organization_id' => $this->org1->id,
            'name' => 'Pohon Kinerja Pertanian 2026',
            'sector_id' => $this->org1Sector->id,
        ]);

        $tree = KinerjaTree::where('name', 'Pohon Kinerja Pertanian 2026')->first();
        $response->assertRedirect(route('kinerja.show', $tree));

        // 2. Show page receives sector in tree payload
        $this->actingAs($this->plannerOrg1)
            ->get("/kinerja/{$tree->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/Show')
                ->where('tree.id', $tree->id)
                ->where('tree.sector_id', $this->org1Sector->id)
                ->where('tree.sector.name', 'Pertanian Berkelanjutan')
            );
    }

    public function test_kinerja_tree_cannot_be_assigned_to_other_org_sector(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->post('/kinerja', [
                'name' => 'Pohon Kinerja Ilegal',
                'sector_id' => $this->org2Sector->id, // belongs to org2
            ])
            ->assertSessionHasErrors(['sector_id']);
    }

    public function test_ai_service_prioritizes_sector_knowledge_pack_when_tree_has_sector(): void
    {
        KnowledgePack::create([
            'sector_id' => $this->org1Sector->id,
            'title' => 'Knowledge Pack Pertanian Spesifik',
            'content' => 'KONTEN_SEKTOR_PERTANIAN_SPESIFIK',
            'is_active' => true,
        ]);

        KnowledgePack::create([
            'sector_id' => $this->globalSector->id,
            'title' => 'Knowledge Pack Pendidikan Global',
            'content' => 'KONTEN_GLOBAL_PENDIDIKAN',
            'is_active' => true,
        ]);

        $treeWithSector = KinerjaTree::create([
            'organization_id' => $this->org1->id,
            'sector_id' => $this->org1Sector->id,
            'name' => 'Pohon Kinerja Bersektor',
            'status' => 'draft',
        ]);

        $node = $treeWithSector->nodes()->create([
            'statement' => 'Meningkatnya Produksi Pangan',
            'type' => 'outcome',
        ]);

        $mockLlm = Mockery::mock(LlmClient::class);
        $mockLlm->shouldReceive('chat')
            ->once()
            ->withArgs(function ($messages, $options) {
                $userMsg = $messages[1]['content'];

                // AI prompt should contain KONTEN_SEKTOR_PERTANIAN_SPESIFIK
                // and should NOT contain KONTEN_GLOBAL_PENDIDIKAN because sector was specified
                return str_contains($userMsg, 'KONTEN_SEKTOR_PERTANIAN_SPESIFIK')
                    && ! str_contains($userMsg, 'KONTEN_GLOBAL_PENDIDIKAN');
            })
            ->andReturn([
                'recommendations' => [
                    [
                        'statement' => 'Tersedianya Pupuk Berkualitas',
                        'relationship_reason' => 'Menunjang pertumbuhan tanaman.',
                        'assumptions' => [],
                        'missing_data' => [],
                    ],
                ],
            ]);

        $this->app->instance(LlmClient::class, $mockLlm);

        $response = $this->actingAs($this->plannerOrg1)
            ->withHeader('X-Inertia', 'false')
            ->post("/kinerja/{$treeWithSector->id}/nodes/{$node->id}/ai/children");

        $response->assertStatus(200);
    }

    public function test_invalid_sector_id_query_falls_back_to_first_sector(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->get('/knowledge-packs?sector_id=99999')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('KnowledgePack/Index')
                ->where('selected_sector_id', $this->globalSector->id)
            );
    }

    public function test_sector_deletion_sets_null_on_associated_kinerja_trees(): void
    {
        $tree = KinerjaTree::create([
            'organization_id' => $this->org1->id,
            'sector_id' => $this->org1Sector->id,
            'name' => 'Tree with sector',
            'status' => 'draft',
        ]);

        $this->actingAs($this->plannerOrg1)
            ->delete("/knowledge-packs/sectors/{$this->org1Sector->id}")
            ->assertRedirect(route('knowledge-packs.index'));

        $this->assertDatabaseMissing('sectors', ['id' => $this->org1Sector->id]);
        $tree->refresh();
        $this->assertNull($tree->sector_id);
    }

    public function test_sector_creation_with_symbol_only_slug_converts_to_null(): void
    {
        $this->actingAs($this->plannerOrg1)
            ->post('/knowledge-packs/sectors', [
                'name' => 'Sektor Simbolik',
                'slug' => '---',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sectors', [
            'name' => 'Sektor Simbolik',
            'slug' => null,
        ]);
    }
}
