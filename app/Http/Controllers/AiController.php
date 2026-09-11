<?php

namespace App\Http\Controllers;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Services\AiRecommendationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiController extends Controller
{
    public function __construct(private AiRecommendationService $ai) {}

    /** Usulkan turunan sasaran untuk satu node. */
    public function recommendChildren(Request $request, KinerjaTree $tree, Node $node)
    {
        abort_unless($tree->organization_id === $request->user()->organization_id, 403);
        abort_unless($node->tree_id === $tree->id, 422);

        $result = $this->ai->recommendChildren($node);

        if ($request->header('X-Inertia') === 'false') {
            return response()->json(['recommendations' => $result['recommendations']]);
        }

        return back()->with('ai_children', $result['recommendations']);
    }

    /** Usulkan indikator untuk satu node. */
    public function recommendIndicators(Request $request, KinerjaTree $tree, Node $node)
    {
        abort_unless($tree->organization_id === $request->user()->organization_id, 403);
        abort_unless($node->tree_id === $tree->id, 422);

        $result = $this->ai->recommendIndicators($node);

        if ($request->header('X-Inertia') === 'false') {
            return response()->json(['indicators' => $result['indicators']]);
        }

        return back()->with('ai_indicators', $result['indicators']);
    }

    /** Terima rekomendasi turunan -> buat node baru. */
    public function acceptChildren(Request $request, KinerjaTree $tree, Node $node)
    {
        abort_unless($tree->organization_id === $request->user()->organization_id, 403);

        $data = $request->validate([
            'statement' => ['required', 'string'],
            'relationship_reason' => ['nullable', 'string'],
        ]);

        $child = $tree->nodes()->create([
            'statement' => $data['statement'],
            'type' => 'outcome',
            'source_type' => 'ai_proposed',
            'order' => $tree->nodes()->count(),
        ]);

        // Hubungkan parent -> child dengan alasan hubungan.
        \App\Models\NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $node->id,
            'child_node_id' => $child->id,
            'reason' => $data['relationship_reason'] ?? null,
        ]);

        return back()->with('success', 'Usulan diterima sebagai sasaran baru.');
    }

    /** Terima rekomendasi indikator -> buat indikator. */
    public function acceptIndicator(Request $request, KinerjaTree $tree, Node $node)
    {
        abort_unless($tree->organization_id === $request->user()->organization_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string'],
            'definition' => ['nullable', 'string'],
            'unit' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:naik,turun,tetap'],
            'data_source' => ['nullable', 'string'],
        ]);

        $node->indicators()->create($data);

        return back()->with('success', 'Indikator usulan disimpan.');
    }
}
