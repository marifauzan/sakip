<?php

namespace App\Http\Controllers;

use App\Models\AiRecommendation;
use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use App\Services\AiRecommendationService;
use Illuminate\Http\Request;

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
            return response()->json([
                'recommendations' => $result['recommendations'],
                'recommendation_id' => $result['recommendation_id'] ?? null,
            ]);
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
            return response()->json([
                'indicators' => $result['indicators'],
                'recommendation_id' => $result['recommendation_id'] ?? null,
            ]);
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
            'recommendation_id' => ['nullable', 'integer'],
        ]);

        $child = $tree->nodes()->create([
            'statement' => $data['statement'],
            'type' => 'outcome',
            'source_type' => 'ai_proposed',
            'order' => $tree->nodes()->count(),
        ]);

        // Hubungkan parent -> child dengan alasan hubungan.
        NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $node->id,
            'child_node_id' => $child->id,
            'reason' => $data['relationship_reason'] ?? null,
        ]);

        if (! empty($data['recommendation_id'])) {
            $rec = AiRecommendation::where('id', $data['recommendation_id'])
                ->where('tree_id', $tree->id)
                ->first();
            if ($rec) {
                $items = collect($rec->output['recommendations'] ?? [])
                    ->reject(fn ($r) => ($r['statement'] ?? '') === $data['statement'])
                    ->values()
                    ->all();
                if (empty($items)) {
                    $rec->update(['decision' => 'accepted', 'output' => ['recommendations' => []]]);
                } else {
                    $rec->update(['output' => ['recommendations' => $items]]);
                }
            }
        }

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
            'recommendation_id' => ['nullable', 'integer'],
        ]);

        $node->indicators()->create([
            'name' => $data['name'],
            'definition' => $data['definition'] ?? null,
            'unit' => $data['unit'] ?? null,
            'direction' => $data['direction'] ?? null,
            'data_source' => $data['data_source'] ?? null,
        ]);

        if (! empty($data['recommendation_id'])) {
            $rec = AiRecommendation::where('id', $data['recommendation_id'])
                ->where('tree_id', $tree->id)
                ->first();
            if ($rec) {
                $items = collect($rec->output['indicators'] ?? [])
                    ->reject(fn ($i) => ($i['name'] ?? '') === $data['name'])
                    ->values()
                    ->all();
                if (empty($items)) {
                    $rec->update(['decision' => 'accepted', 'output' => ['indicators' => []]]);
                } else {
                    $rec->update(['output' => ['indicators' => $items]]);
                }
            }
        }

        return back()->with('success', 'Indikator usulan disimpan.');
    }

    /** Abaikan / tolak rekomendasi usulan AI. */
    public function dismissRecommendations(Request $request, KinerjaTree $tree, Node $node)
    {
        abort_unless($tree->organization_id === $request->user()->organization_id, 403);
        abort_unless($node->tree_id === $tree->id, 422);

        $kind = $request->input('kind');

        $query = AiRecommendation::query()
            ->where('tree_id', $tree->id)
            ->where('node_id', $node->id)
            ->where('decision', 'pending');

        if ($kind) {
            $query->where('kind', $kind);
        }

        $query->update(['decision' => 'rejected']);

        if ($request->header('X-Inertia') === 'false' || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Usulan AI diabaikan.');
    }
}
