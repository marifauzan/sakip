<?php

namespace Tests\Feature;

use App\Models\KinerjaTree;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KinerjaTreeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $org;

    private KinerjaTree $tree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Kementerian Pertanian',
            'type' => 'kementerian_lembaga',
            'code' => 'KEMENTAN',
        ]);

        $this->user = User::create([
            'name' => 'Perencana Kementan',
            'email' => 'planner@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $this->org->id,
            'role' => 'planner',
        ]);

        $this->tree = KinerjaTree::create([
            'organization_id' => $this->org->id,
            'name' => 'Renstra Kementan 2025-2029',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'draft',
        ]);
    }

    public function test_can_view_kinerja_index(): void
    {
        $this->actingAs($this->user)
            ->get('/kinerja')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/Index')
                ->has('trees', 1)
            );
    }

    public function test_can_create_new_kinerja_tree(): void
    {
        $response = $this->actingAs($this->user)->post('/kinerja', [
            'name' => 'Pohon Kinerja Ditjen Tanaman Pangan',
            'period_start' => 2025,
            'period_end' => 2029,
        ]);

        $this->assertDatabaseHas('kinerja_trees', [
            'organization_id' => $this->org->id,
            'name' => 'Pohon Kinerja Ditjen Tanaman Pangan',
            'status' => 'draft',
        ]);

        $newTree = KinerjaTree::where('name', 'Pohon Kinerja Ditjen Tanaman Pangan')->first();
        $response->assertRedirect(route('kinerja.show', $newTree));
    }

    public function test_can_view_kinerja_show(): void
    {
        $this->actingAs($this->user)
            ->get("/kinerja/{$this->tree->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/Show')
                ->where('tree.id', $this->tree->id)
                ->where('tree.name', 'Renstra Kementan 2025-2029')
            );
    }

    public function test_can_add_node_to_tree(): void
    {
        $response = $this->actingAs($this->user)->post("/kinerja/{$this->tree->id}/nodes", [
            'statement' => 'Meningkatnya Produksi Pangan Nasional',
            'type' => 'outcome',
            'code' => 'SS.1',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('nodes', [
            'tree_id' => $this->tree->id,
            'statement' => 'Meningkatnya Produksi Pangan Nasional',
            'type' => 'outcome',
            'code' => 'SS.1',
            'source_type' => 'user_edited',
        ]);
    }

    public function test_node_creation_validates_required_and_in_rule(): void
    {
        $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/nodes", [
                'statement' => '',
                'type' => 'invalid_type',
            ])
            ->assertSessionHasErrors(['statement', 'type']);
    }

    public function test_can_add_indicator_to_node(): void
    {
        $node = $this->tree->nodes()->create([
            'statement' => 'Meningkatnya Produksi Beras',
            'type' => 'outcome',
        ]);

        $response = $this->actingAs($this->user)->post("/kinerja/{$this->tree->id}/nodes/{$node->id}/indicators", [
            'name' => 'Volume Produksi Beras Nasional',
            'unit' => 'Juta Ton',
            'direction' => 'naik',
            'definition' => 'Total produksi gabah kering giling dikonversi ke beras',
            'data_source' => 'BPS & Kementan',
            'baseline' => '31.5',
            'target' => '35.0',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('indicators', [
            'node_id' => $node->id,
            'name' => 'Volume Produksi Beras Nasional',
            'unit' => 'Juta Ton',
            'direction' => 'naik',
            'target' => '35.0',
        ]);
    }

    public function test_cannot_add_indicator_to_node_from_another_tree(): void
    {
        $otherTree = KinerjaTree::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Tree',
        ]);

        $otherNode = $otherTree->nodes()->create([
            'statement' => 'Other Node',
            'type' => 'outcome',
        ]);

        $this->actingAs($this->user)
            ->post("/kinerja/{$this->tree->id}/nodes/{$otherNode->id}/indicators", [
                'name' => 'Indikator Nyasar',
            ])
            ->assertStatus(422);
    }

    public function test_can_add_node_with_parent_link(): void
    {
        $parent = $this->tree->nodes()->create([
            'statement' => 'Meningkatnya Kedaulatan Pangan',
            'type' => 'outcome',
            'code' => 'SS.0',
        ]);

        $response = $this->actingAs($this->user)->post("/kinerja/{$this->tree->id}/nodes", [
            'statement' => 'Tercapainya Swasembada Padi',
            'type' => 'output',
            'code' => 'SP.1',
            'parent_node_id' => $parent->id,
            'relationship_reason' => 'Kondisi esensial kedaulatan pangan',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('nodes', [
            'tree_id' => $this->tree->id,
            'statement' => 'Tercapainya Swasembada Padi',
            'type' => 'output',
        ]);

        $child = $this->tree->nodes()->where('statement', 'Tercapainya Swasembada Padi')->first();

        $this->assertDatabaseHas('node_links', [
            'tree_id' => $this->tree->id,
            'parent_node_id' => $parent->id,
            'child_node_id' => $child->id,
            'reason' => 'Kondisi esensial kedaulatan pangan',
        ]);
    }
}
