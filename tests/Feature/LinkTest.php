<?php

namespace Tests\Feature;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private KinerjaTree $tree;

    private Node $nodeParent;

    private Node $nodeChild;

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
            'name' => 'Pohon Kinerja',
            'status' => 'draft',
        ]);

        $this->nodeParent = $this->tree->nodes()->create([
            'statement' => 'Terwujudnya Kedaulatan Pangan',
            'type' => 'outcome',
        ]);

        $this->nodeChild = $this->tree->nodes()->create([
            'statement' => 'Meningkatnya Produksi Padi dan Jagung',
            'type' => 'outcome',
        ]);
    }

    public function test_can_link_parent_to_child_successfully(): void
    {
        $response = $this->actingAs($this->user)->post("/kinerja/{$this->tree->id}/links", [
            'parent_node_id' => $this->nodeParent->id,
            'child_node_id' => $this->nodeChild->id,
            'reason' => 'Produksi padi dan jagung yang memadai merupakan syarat utama kedaulatan pangan.',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('node_links', [
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeParent->id,
            'child_node_id' => $this->nodeChild->id,
            'reason' => 'Produksi padi dan jagung yang memadai merupakan syarat utama kedaulatan pangan.',
        ]);
    }

    public function test_cannot_link_nodes_from_different_trees(): void
    {
        $otherTree = KinerjaTree::create([
            'organization_id' => $this->user->organization_id,
            'name' => 'Pohon Lain',
        ]);

        $foreignNode = $otherTree->nodes()->create([
            'statement' => 'Sasaran Luar',
            'type' => 'outcome',
        ]);

        $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/links", [
                'parent_node_id' => $this->nodeParent->id,
                'child_node_id' => $foreignNode->id,
            ])
            ->assertStatus(422);
    }

    public function test_link_that_creates_cycle_is_rejected(): void
    {
        // Existing link: Parent -> Child
        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeParent->id,
            'child_node_id' => $this->nodeChild->id,
        ]);

        // Attempt reverse link: Child -> Parent (cycle)
        $response = $this->actingAs($this->user)->post("/kinerja/{$this->tree->id}/links", [
            'parent_node_id' => $this->nodeChild->id,
            'child_node_id' => $this->nodeParent->id,
        ]);

        $response->assertSessionHasErrors(['link']);

        $this->assertDatabaseMissing('node_links', [
            'parent_node_id' => $this->nodeChild->id,
            'child_node_id' => $this->nodeParent->id,
        ]);
    }
}
