<?php

namespace App\Http\Controllers;

use App\Models\KinerjaTree;
use App\Services\KinerjaExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends Controller
{
    /** Tambah reviu/komentar pada rancangan atau simpul. */
    public function store(Request $request, KinerjaTree $tree)
    {
        abort_unless($tree->organization_id === $request->user()->organization_id, 403);

        $data = $request->validate([
            'node_id' => ['nullable', 'integer', 'exists:nodes,id'],
            'decision' => ['required', 'in:comment,approve,reject'],
            'comment' => ['nullable', 'string'],
        ]);

        $tree->reviews()->create([
            'node_id' => $data['node_id'] ?? null,
            'user_id' => $request->user()->id,
            'decision' => $data['decision'],
            'comment' => $data['comment'] ?? null,
        ]);

        // Jika approve node, tandai source_type = approved
        if ($data['decision'] === 'approve' && ! empty($data['node_id'])) {
            \App\Models\Node::where('id', $data['node_id'])
                ->where('tree_id', $tree->id)
                ->update(['source_type' => 'approved']);
        }

        return back()->with('success', 'Reviu disimpan.');
    }

    /** Ekspor rancangan (Markdown / JSON). */
    public function export(Request $request, KinerjaTree $tree, KinerjaExporter $exporter)
    {
        abort_unless($tree->organization_id === $request->user()->organization_id, 403);

        $format = $request->query('format', 'markdown');

        $filename = 'sakip-'.str()->slug($tree->name).'.'.($format === 'json' ? 'json' : 'md');

        if ($format === 'json') {
            $content = $exporter->toJson($tree);
            $contentType = 'application/json';
        } else {
            $content = $exporter->toMarkdown($tree);
            $contentType = 'text/markdown';
        }

        return response()->streamDownload(
            fn () => print($content),
            $filename,
            ['Content-Type' => $contentType]
        );
    }
}
