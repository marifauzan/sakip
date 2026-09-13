<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\KinerjaTree;
use App\Models\KnowledgePack;
use App\Models\Node;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing('organization');
        $orgId = $user->organization_id;

        $treeCount = $orgId ? KinerjaTree::where('organization_id', $orgId)->count() : 0;
        $docCount = $orgId ? Document::where('organization_id', $orgId)->count() : 0;
        $nodeCount = $orgId ? Node::whereHas('tree', fn ($q) => $q->where('organization_id', $orgId))->count() : 0;

        $kpCount = KnowledgePack::where('is_active', true)
            ->whereHas('sector', function ($q) use ($orgId) {
                $q->whereNull('organization_id')
                    ->when($orgId, fn ($query) => $query->orWhere('organization_id', $orgId));
            })
            ->count();

        $recentTrees = $orgId
            ? KinerjaTree::where('organization_id', $orgId)
                ->with(['sector:id,name'])
                ->withCount(['nodes', 'links'])
                ->latest()
                ->limit(4)
                ->get()
            : collect();

        $recentDocuments = $orgId
            ? Document::where('organization_id', $orgId)
                ->latest()
                ->limit(4)
                ->get()
            : collect();

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => $user->only('id', 'name', 'email', 'role'),
                'organization' => $user->organization?->only('id', 'name', 'type', 'code'),
            ],
            'metrics' => [
                'trees_count' => $treeCount,
                'documents_count' => $docCount,
                'knowledge_packs_count' => $kpCount,
                'nodes_count' => $nodeCount,
            ],
            'recentTrees' => $recentTrees,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
