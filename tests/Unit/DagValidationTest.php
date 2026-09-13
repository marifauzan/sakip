<?php

namespace Tests\Unit;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use App\Models\Organization;
use App\Services\DagValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DagValidationTest extends TestCase
{
    use RefreshDatabase;

    private KinerjaTree $tree;

    private Node $nodeA;

    private Node $nodeB;

    private Node $nodeC;

    private Node $nodeD;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Bappeda Test',
            'type' => 'pemerintah_daerah',
            'code' => 'BAPPEDA',
        ]);

        $this->tree = KinerjaTree::create([
            'organization_id' => $org->id,
            'name' => 'Renstra Bappeda 2025-2029',
            'status' => 'draft',
        ]);

        $this->nodeA = $this->tree->nodes()->create(['statement' => 'Sasaran A', 'type' => 'outcome']);
        $this->nodeB = $this->tree->nodes()->create(['statement' => 'Sasaran B', 'type' => 'outcome']);
        $this->nodeC = $this->tree->nodes()->create(['statement' => 'Sasaran C', 'type' => 'output']);
        $this->nodeD = $this->tree->nodes()->create(['statement' => 'Sasaran D', 'type' => 'aktivitas']);
    }

    public function test_self_link_creates_cycle(): void
    {
        $this->assertTrue(DagValidator::wouldCreateCycle($this->tree, $this->nodeA->id, $this->nodeA->id));
    }

    public function test_valid_link_does_not_create_cycle(): void
    {
        $this->assertFalse(DagValidator::wouldCreateCycle($this->tree, $this->nodeA->id, $this->nodeB->id));
    }

    public function test_direct_cycle_is_detected(): void
    {
        // Link A -> B
        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeA->id,
            'child_node_id' => $this->nodeB->id,
        ]);

        // Attempt B -> A
        $this->assertTrue(DagValidator::wouldCreateCycle($this->tree, $this->nodeB->id, $this->nodeA->id));
    }

    public function test_indirect_cycle_chain_is_detected(): void
    {
        // A -> B -> C
        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeA->id,
            'child_node_id' => $this->nodeB->id,
        ]);
        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeB->id,
            'child_node_id' => $this->nodeC->id,
        ]);

        // Attempt C -> A (loop: A -> B -> C -> A)
        $this->assertTrue(DagValidator::wouldCreateCycle($this->tree, $this->nodeC->id, $this->nodeA->id));

        // But C -> D is valid
        $this->assertFalse(DagValidator::wouldCreateCycle($this->tree, $this->nodeC->id, $this->nodeD->id));
    }

    public function test_diamond_dag_is_allowed(): void
    {
        // A -> B -> D and A -> C -> D (Valid diamond DAG, not a cycle)
        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeA->id,
            'child_node_id' => $this->nodeB->id,
        ]);
        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeA->id,
            'child_node_id' => $this->nodeC->id,
        ]);
        NodeLink::create([
            'tree_id' => $this->tree->id,
            'parent_node_id' => $this->nodeB->id,
            'child_node_id' => $this->nodeD->id,
        ]);

        // Linking C -> D should NOT create cycle
        $this->assertFalse(DagValidator::wouldCreateCycle($this->tree, $this->nodeC->id, $this->nodeD->id));
    }
}
