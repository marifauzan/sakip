<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use App\Services\DagValidator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KinerjaController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->organization_id;

        $trees = KinerjaTree::query()
            ->where('organization_id', $org)
            ->withCount('nodes')
            ->latest()
            ->get()
            ->map(fn (KinerjaTree $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'period_start' => $t->period_start,
                'period_end' => $t->period_end,
                'status' => $t->status,
                'nodes_count' => $t->nodes_count,
            ]);

        return Inertia::render('Kinerja/Index', ['trees' => $trees]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_end' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $tree = KinerjaTree::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $data['name'],
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('kinerja.show', $tree);
    }

    public function show(Request $request, KinerjaTree $tree)
    {
        $this->authorizeTree($request, $tree);

        $tree->load(['nodes.indicators', 'links', 'reviews.user']);

        return Inertia::render('Kinerja/Show', [
            'tree' => [
                'id' => $tree->id,
                'name' => $tree->name,
                'status' => $tree->status,
                'nodes' => $tree->nodes->map(fn (Node $n) => [
                    'id' => $n->id,
                    'code' => $n->code,
                    'statement' => $n->statement,
                    'type' => $n->type,
                    'source_type' => $n->source_type,
                    'order' => $n->order,
                    'indicators' => $n->indicators->map(fn (Indicator $i) => [
                        'id' => $i->id,
                        'name' => $i->name,
                        'unit' => $i->unit,
                        'direction' => $i->direction,
                        'baseline' => $i->baseline,
                        'target' => $i->target,
                    ]),
                ]),
                'links' => $tree->links->map(fn (NodeLink $l) => [
                    'id' => $l->id,
                    'parent_node_id' => $l->parent_node_id,
                    'child_node_id' => $l->child_node_id,
                    'reason' => $l->reason,
                ]),
                'reviews' => $tree->reviews->map(fn ($r) => [
                    'id' => $r->id,
                    'node_id' => $r->node_id,
                    'decision' => $r->decision,
                    'comment' => $r->comment,
                    'user' => $r->user?->name,
                    'created_at' => $r->created_at?->toISOString(),
                ]),
            ],
        ]);
    }

    public function storeNode(Request $request, KinerjaTree $tree)
    {
        $this->authorizeTree($request, $tree);

        $data = $request->validate([
            'statement' => ['required', 'string'],
            'type' => ['required', 'in:outcome,output,aktivitas'],
            'code' => ['nullable', 'string', 'max:64'],
        ]);

        $node = $tree->nodes()->create([
            'statement' => $data['statement'],
            'type' => $data['type'],
            'code' => $data['code'] ?? null,
            'source_type' => 'user_edited',
            'order' => $tree->nodes()->count(),
        ]);

        return back()->with('success', 'Sasaran ditambahkan.');
    }

    public function storeLink(Request $request, KinerjaTree $tree)
    {
        $this->authorizeTree($request, $tree);

        $data = $request->validate([
            'parent_node_id' => ['required', 'integer', 'exists:nodes,id'],
            'child_node_id' => ['required', 'integer', 'exists:nodes,id'],
            'reason' => ['nullable', 'string'],
        ]);

        // Pastikan kedua node milik tree ini (bukan lintas tree/org).
        $belongsToTree = Node::whereIn('id', [$data['parent_node_id'], $data['child_node_id']])
            ->where('tree_id', $tree->id)
            ->count() === 2;

        abort_unless($belongsToTree, 422, 'Node harus berada dalam rancangan yang sama.');

        if (DagValidator::wouldCreateCycle($tree, (int) $data['parent_node_id'], (int) $data['child_node_id'])) {
            return back()->withErrors(['link' => 'Hubungan ini akan membentuk siklus (loop). Ditolak.']);
        }

        NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $data['parent_node_id'],
            'child_node_id' => $data['child_node_id'],
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Hubungan ditambahkan.');
    }

    public function storeIndicator(Request $request, KinerjaTree $tree, Node $node)
    {
        $this->authorizeTree($request, $tree);
        abort_unless($node->tree_id === $tree->id, 422, 'Node tidak dalam rancangan ini.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', 'in:naik,turun,tetap'],
            'definition' => ['nullable', 'string'],
            'data_source' => ['nullable', 'string', 'max:255'],
            'baseline' => ['nullable', 'string', 'max:64'],
            'target' => ['nullable', 'string', 'max:64'],
        ]);

        $node->indicators()->create($data);

        return back()->with('success', 'Indikator ditambahkan.');
    }

    private function authorizeTree(Request $request, KinerjaTree $tree): void
    {
        abort_unless(
            $tree->organization_id === $request->user()->organization_id,
            403,
            'Anda tidak memiliki akses ke rancangan ini.'
        );
    }
}
