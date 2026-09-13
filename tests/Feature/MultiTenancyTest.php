<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\KinerjaTree;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    private User $userOrg1;

    private User $userOrg2;

    private Organization $org1;

    private Organization $org2;

    private KinerjaTree $treeOrg1;

    private Document $docOrg1;

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

        $this->userOrg1 = User::create([
            'name' => 'Planner Kementan',
            'email' => 'planner@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org1->id,
            'role' => 'planner',
        ]);

        $this->userOrg2 = User::create([
            'name' => 'Planner Kemenag',
            'email' => 'planner@kemenag.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org2->id,
            'role' => 'planner',
        ]);

        $this->treeOrg1 = KinerjaTree::create([
            'organization_id' => $this->org1->id,
            'name' => 'Renstra Kementan 2025-2029',
            'status' => 'draft',
        ]);

        $this->docOrg1 = Document::create([
            'organization_id' => $this->org1->id,
            'title' => 'Renstra Kementan PDF',
            'type' => 'renstra',
            'file_path' => 'documents/kementan.pdf',
            'file_name' => 'kementan.pdf',
            'status' => 'extracted',
        ]);
    }

    public function test_user_cannot_access_foreign_organization_document(): void
    {
        $this->actingAs($this->userOrg2)
            ->get("/documents/{$this->docOrg1->id}")
            ->assertStatus(403);
    }

    public function test_user_can_access_own_organization_document(): void
    {
        $this->actingAs($this->userOrg1)
            ->get("/documents/{$this->docOrg1->id}")
            ->assertStatus(200);
    }

    public function test_user_cannot_access_or_modify_foreign_kinerja_tree(): void
    {
        // View
        $this->actingAs($this->userOrg2)
            ->get("/kinerja/{$this->treeOrg1->id}")
            ->assertStatus(403);

        // Store Node
        $this->actingAs($this->userOrg2)
            ->post("/kinerja/{$this->treeOrg1->id}/nodes", [
                'statement' => 'Sasaran Ilegal',
                'type' => 'outcome',
            ])
            ->assertStatus(403);

        // Store Link
        $this->actingAs($this->userOrg2)
            ->post("/kinerja/{$this->treeOrg1->id}/links", [
                'parent_node_id' => 1,
                'child_node_id' => 2,
            ])
            ->assertStatus(403);

        // Store Review
        $this->actingAs($this->userOrg2)
            ->post("/kinerja/{$this->treeOrg1->id}/reviews", [
                'decision' => 'comment',
                'comment' => 'Komentar tidak sah',
            ])
            ->assertStatus(403);

        // Export
        $this->actingAs($this->userOrg2)
            ->get("/kinerja/{$this->treeOrg1->id}/export")
            ->assertStatus(403);
    }

    public function test_index_lists_are_scoped_to_current_organization(): void
    {
        $treeOrg2 = KinerjaTree::create([
            'organization_id' => $this->org2->id,
            'name' => 'Renstra Kemenag 2025-2029',
            'status' => 'draft',
        ]);

        $this->actingAs($this->userOrg1)
            ->get('/kinerja')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/Index')
                ->has('trees', 1)
                ->where('trees.0.name', 'Renstra Kementan 2025-2029')
            );

        $this->actingAs($this->userOrg2)
            ->get('/kinerja')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/Index')
                ->has('trees', 1)
                ->where('trees.0.name', 'Renstra Kemenag 2025-2029')
            );
    }
}
