<?php

namespace App\Services;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use Illuminate\Support\Collection;

/**
 * Ekspor rancangan perjenjangan kinerja ke format Markdown / JSON / CSV.
 */
class KinerjaExporter
{
    /** Ekspor ke Markdown (hierarki + tabel indikator). */
    public function toMarkdown(KinerjaTree $tree): string
    {
        $tree->load(['nodes.indicators', 'links']);

        $lines = [];
        $lines[] = '# '.$tree->name;
        $lines[] = '';
        $lines[] = 'Periode: '.(($tree->period_start && $tree->period_end)
            ? $tree->period_start.'–'.$tree->period_end
            : '—');
        $lines[] = 'Status: '.$tree->status;
        $lines[] = '';

        // Root nodes = node yang tidak punya parent link.
        $roots = $this->roots($tree);
        foreach ($roots as $root) {
            $this->renderNode($tree, $root, 0, $lines);
        }

        // Tabel indikator
        $lines[] = '';
        $lines[] = '## Indikator';
        $lines[] = '';
        $lines[] = '| Sasaran | Indikator | Satuan | Arah | Baseline | Target |';
        $lines[] = '|---|---|---|---|---|---|';
        foreach ($tree->nodes as $node) {
            foreach ($node->indicators as $ind) {
                $lines[] = implode(' | ', [
                    $this->escape($node->statement),
                    $this->escape($ind->name),
                    $this->escape($ind->unit ?? ''),
                    $ind->direction ?? '',
                    $this->escape($ind->baseline ?? ''),
                    $this->escape($ind->target ?? ''),
                ]);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /** Ekspor ke JSON (struktur lengkap). */
    public function toJson(KinerjaTree $tree): string
    {
        $tree->load(['nodes.indicators', 'links']);

        return json_encode([
            'name' => $tree->name,
            'period_start' => $tree->period_start,
            'period_end' => $tree->period_end,
            'status' => $tree->status,
            'nodes' => $tree->nodes->map(fn (Node $n) => [
                'id' => $n->id,
                'code' => $n->code,
                'statement' => $n->statement,
                'type' => $n->type,
                'source_type' => $n->source_type,
                'indicators' => $n->indicators->map(fn ($i) => [
                    'name' => $i->name,
                    'definition' => $i->definition,
                    'unit' => $i->unit,
                    'direction' => $i->direction,
                    'data_source' => $i->data_source,
                    'baseline' => $i->baseline,
                    'target' => $i->target,
                ]),
            ]),
            'links' => $tree->links->map(fn (NodeLink $l) => [
                'parent' => $l->parent_node_id,
                'child' => $l->child_node_id,
                'reason' => $l->reason,
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** @return Collection<int, Node> */
    private function roots(KinerjaTree $tree): Collection
    {
        $childIds = $tree->links->pluck('child_node_id')->unique();

        return $tree->nodes->whereNotIn('id', $childIds);
    }

    private function renderNode(KinerjaTree $tree, Node $node, int $depth, array &$lines): void
    {
        $indent = str_repeat('  ', $depth);
        $code = $node->code ? "[{$node->code}] " : '';
        $lines[] = $indent.'- '.$code.$node->statement;

        // Anak-anak
        $children = $tree->links
            ->where('parent_node_id', $node->id)
            ->map(fn ($l) => $tree->nodes->firstWhere('id', $l->child_node_id))
            ->filter();
        foreach ($children as $child) {
            $this->renderNode($tree, $child, $depth + 1, $lines);
        }
    }

    private function escape(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }
}
