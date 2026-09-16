<?php

namespace Tests\Feature;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodePositionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private User $userB;
    private KinerjaTree $tree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Org A', 'type' => 'kementerian_lembaga']);
        $this->orgB = Organization::create(['name' => 'Org B', 'type' => 'pemerintah_daerah']);

        $this->userA = User::create([
            'name' => 'A', 'email' => 'a@test.test', 'password' => bcrypt('x'),
            'organization_id' => $this->orgA->id, 'role' => 'planner',
        ]);
        $this->userB = User::create([
            'name' => 'B', 'email' => 'b@test.test', 'password' => bcrypt('x'),
            'organization_id' => $this->orgB->id, 'role' => 'planner',
        ]);

        $this->tree = KinerjaTree::create([
            'organization_id' => $this->orgA->id, 'name' => 'Tree A', 'status' => 'draft',
        ]);
    }

    private function makeNode(KinerjaTree $tree, string $statement): Node
    {
        return $tree->nodes()->create([
            'statement' => $statement, 'type' => 'outcome', 'source_type' => 'user_edited',
        ]);
    }

    public function test_can_save_node_positions(): void
    {
        $node = $this->makeNode($this->tree, 'Sasaran A');

        $this->actingAs($this->userA)
            ->postJson("/kinerja/{$this->tree->id}/positions", [
                'positions' => [
                    ['id' => $node->id, 'pos_x' => 120.5, 'pos_y' => 340.0],
                ],
            ])
            ->assertOk()
            ->assertJson(['saved' => 1]);

        $node->refresh();
        $this->assertEquals(120.5, $node->pos_x);
        $this->assertEquals(340.0, $node->pos_y);
    }

    public function test_position_update_ignores_nodes_from_other_tree(): void
    {
        $mine = $this->makeNode($this->tree, 'Sasaran saya');

        $otherTree = KinerjaTree::create([
            'organization_id' => $this->orgA->id, 'name' => 'Tree lain', 'status' => 'draft',
        ]);
        $foreign = $this->makeNode($otherTree, 'Sasaran lain');

        $this->actingAs($this->userA)
            ->postJson("/kinerja/{$this->tree->id}/positions", [
                'positions' => [
                    ['id' => $mine->id, 'pos_x' => 10, 'pos_y' => 20],
                    ['id' => $foreign->id, 'pos_x' => 999, 'pos_y' => 999],
                ],
            ])
            ->assertOk();

        // Node milik tree lain TIDAK boleh berubah.
        $foreign->refresh();
        $this->assertNull($foreign->pos_x);
        $this->assertNull($foreign->pos_y);
    }

    public function test_user_cannot_save_positions_for_other_organization(): void
    {
        $node = $this->makeNode($this->tree, 'Sasaran A');

        $this->actingAs($this->userB)
            ->postJson("/kinerja/{$this->tree->id}/positions", [
                'positions' => [['id' => $node->id, 'pos_x' => 1, 'pos_y' => 2]],
            ])
            ->assertForbidden();
    }

    public function test_positions_validation_requires_coordinates(): void
    {
        $node = $this->makeNode($this->tree, 'Sasaran A');

        $this->actingAs($this->userA)
            ->postJson("/kinerja/{$this->tree->id}/positions", [
                'positions' => [['id' => $node->id]],
            ])
            ->assertStatus(422);
    }

    public function test_tree_show_exposes_saved_positions(): void
    {
        $node = $this->makeNode($this->tree, 'Sasaran A');
        $node->update(['pos_x' => 77, 'pos_y' => 88]);

        $this->actingAs($this->userA)
            ->get("/kinerja/{$this->tree->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Kinerja/Show')
                ->where('tree.nodes.0.pos_x', 77)
                ->where('tree.nodes.0.pos_y', 88)
            );
    }

    public function test_can_create_link_between_siblings(): void
    {
        $a = $this->makeNode($this->tree, 'A');
        $b = $this->makeNode($this->tree, 'B');

        $this->actingAs($this->userA)
            ->post("/kinerja/{$this->tree->id}/links", [
                'parent_node_id' => $a->id,
                'child_node_id' => $b->id,
            ]);

        $this->assertDatabaseHas('node_links', [
            'tree_id' => $this->tree->id,
            'parent_node_id' => $a->id,
            'child_node_id' => $b->id,
        ]);
    }

    public function test_link_creating_cycle_is_rejected(): void
    {
        $a = $this->makeNode($this->tree, 'A');
        $b = $this->makeNode($this->tree, 'B');

        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $a->id,
            'child_node_id' => $b->id,
        ]);

        // B -> A akan membentuk siklus.
        $this->actingAs($this->userA)
            ->post("/kinerja/{$this->tree->id}/links", [
                'parent_node_id' => $b->id,
                'child_node_id' => $a->id,
            ])
            ->assertSessionHasErrors('link');

        $this->assertDatabaseMissing('node_links', [
            'parent_node_id' => $b->id,
            'child_node_id' => $a->id,
        ]);
    }
}
