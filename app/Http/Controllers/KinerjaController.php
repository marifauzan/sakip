<?php

namespace App\Http\Controllers;

use App\Models\AiRecommendation;
use App\Models\Indicator;
use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use App\Models\Sector;
use App\Services\DagValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KinerjaController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->organization_id;

        $trees = KinerjaTree::query()
            ->where('organization_id', $org)
            ->with(['sector:id,name'])
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
                'sector_id' => $t->sector_id,
                'sector' => $t->sector ? [
                    'id' => $t->sector->id,
                    'name' => $t->sector->name,
                ] : null,
            ]);

        $sectors = Sector::query()
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $org))
            ->orderByRaw('CASE WHEN organization_id IS NULL THEN 0 ELSE 1 END, name ASC')
            ->get(['id', 'name', 'organization_id'])
            ->map(fn (Sector $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_global' => is_null($s->organization_id),
            ]);

        return Inertia::render('Kinerja/Index', [
            'trees' => $trees,
            'sectors' => $sectors,
        ]);
    }

    public function store(Request $request)
    {
        $org = $request->user()->organization_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_end' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'sector_id' => [
                'nullable',
                Rule::exists('sectors', 'id')->where(function ($q) use ($org) {
                    $q->whereNull('organization_id')->orWhere('organization_id', $org);
                }),
            ],
        ]);

        $tree = KinerjaTree::create([
            'organization_id' => $org,
            'sector_id' => $data['sector_id'] ?? null,
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

        $tree->load(['sector:id,name,description', 'nodes.indicators', 'links', 'reviews.user']);

        $pendingRecs = AiRecommendation::query()
            ->where('tree_id', $tree->id)
            ->where('decision', 'pending')
            ->latest('id')
            ->get();

        $pendingIndicatorsByNode = $pendingRecs->where('kind', AiRecommendation::KIND_INDICATORS)->keyBy('node_id');
        $pendingChildrenByNode = $pendingRecs->where('kind', AiRecommendation::KIND_CHILDREN)->keyBy('node_id');

        return Inertia::render('Kinerja/Show', [
            'tree' => [
                'id' => $tree->id,
                'name' => $tree->name,
                'status' => $tree->status,
                'sector_id' => $tree->sector_id,
                'sector' => $tree->sector ? [
                    'id' => $tree->sector->id,
                    'name' => $tree->sector->name,
                    'description' => $tree->sector->description,
                ] : null,
                'nodes' => $tree->nodes->map(fn (Node $n) => [
                    'id' => $n->id,
                    'code' => $n->code,
                    'statement' => $n->statement,
                    'type' => $n->type,
                    'source_type' => $n->source_type,
                    'order' => $n->order,
                    'pos_x' => $n->pos_x,
                    'pos_y' => $n->pos_y,
                    'indicators' => $n->indicators->map(fn (Indicator $i) => [
                        'id' => $i->id,
                        'name' => $i->name,
                        'unit' => $i->unit,
                        'direction' => $i->direction,
                        'baseline' => $i->baseline,
                        'target' => $i->target,
                    ]),
                    'pending_ai_indicators' => $pendingIndicatorsByNode->get($n->id)?->output['indicators'] ?? [],
                    'pending_ai_children' => $pendingChildrenByNode->get($n->id)?->output['recommendations'] ?? [],
                    'pending_indicator_recommendation_id' => $pendingIndicatorsByNode->get($n->id)?->id,
                    'pending_child_recommendation_id' => $pendingChildrenByNode->get($n->id)?->id,
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
            'parent_node_id' => ['nullable', 'integer', 'exists:nodes,id'],
            'relationship_reason' => ['nullable', 'string'],
        ]);

        $node = $tree->nodes()->create([
            'statement' => $data['statement'],
            'type' => $data['type'],
            'code' => $data['code'] ?? null,
            'source_type' => 'user_edited',
            'order' => $tree->nodes()->count(),
        ]);

        if (! empty($data['parent_node_id'])) {
            $parent = Node::where('id', $data['parent_node_id'])
                ->where('tree_id', $tree->id)
                ->first();

            if ($parent && ! DagValidator::wouldCreateCycle($tree, (int) $parent->id, (int) $node->id)) {
                NodeLink::create([
                    'tree_id' => $tree->id,
                    'parent_node_id' => $parent->id,
                    'child_node_id' => $node->id,
                    'reason' => $data['relationship_reason'] ?? null,
                ]);
            }
        }

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

    /**
     * Simpan posisi visual simpul (hasil drag di canvas).
     * Menerima array node: [{id, pos_x, pos_y}, ...].
     */
    public function savePositions(Request $request, KinerjaTree $tree)
    {
        $this->authorizeTree($request, $tree);

        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.id' => ['required', 'integer'],
            'positions.*.pos_x' => ['required', 'numeric'],
            'positions.*.pos_y' => ['required', 'numeric'],
        ]);

        // Ambil hanya node milik tree ini -> cegah update lintas tree/tenant.
        $validIds = Node::where('tree_id', $tree->id)->pluck('id')->all();

        foreach ($data['positions'] as $pos) {
            if (! in_array($pos['id'], $validIds, true)) {
                continue; // lewati node yang bukan milik tree ini
            }

            Node::where('id', $pos['id'])->update([
                'pos_x' => $pos['pos_x'],
                'pos_y' => $pos['pos_y'],
            ]);
        }

        return response()->json(['saved' => count($data['positions'])]);
    }
}
