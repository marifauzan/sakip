<?php

namespace App\Http\Controllers;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Services\KinerjaExporter;
use Illuminate\Http\Request;

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
            Node::where('id', $data['node_id'])
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

        $ext = match ($format) {
            'json' => 'json',
            'docx' => 'docx',
            'pdf' => 'pdf',
            default => 'md',
        };

        $filename = 'sakip-'.str()->slug($tree->name).'.'.$ext;

        $content = match ($format) {
            'json' => $exporter->toJson($tree),
            'docx' => $exporter->toDocx($tree),
            'pdf' => $exporter->toPdf($tree),
            default => $exporter->toMarkdown($tree),
        };

        $contentType = match ($format) {
            'json' => 'application/json',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf' => 'application/pdf',
            default => 'text/markdown',
        };

        return response()->streamDownload(
            fn () => print($content),
            $filename,
            ['Content-Type' => $contentType]
        );
    }
}
