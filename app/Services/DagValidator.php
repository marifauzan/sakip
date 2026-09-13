<?php

namespace App\Services;

use App\Models\KinerjaTree;
use App\Models\NodeLink;

/**
 * Validasi Directed Acyclic Graph (DAG) untuk hubungan antarsasaran.
 * Mencegah siklus: A -> B dan B -> A (atau A -> B -> C -> A).
 */
class DagValidator
{
    /**
     * Cek apakah menambahkan link parent->child akan membentuk siklus.
     * Siklus terjadi jika child (atau keturunannya) sudah menjadi
     * ancestor dari parent.
     */
    public static function wouldCreateCycle(KinerjaTree $tree, int $parentId, int $childId): bool
    {
        if ($parentId === $childId) {
            return true;
        }

        // Menambah edge parent->child membentuk siklus jika child adalah
        // ancestor dari parent (yaitu ada path child -> ... -> parent).
        // Cara cek: telusuri NAIK dari parent (ikut parentLinks).
        // Jika kita menemukan child, berarti child adalah ancestor parent
        // -> edge parent->child akan menutup loop.
        $visited = [];
        $stack = [$parentId];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            if ($current === $childId) {
                return true;
            }

            $parents = NodeLink::where('child_node_id', $current)
                ->where('tree_id', $tree->id)
                ->pluck('parent_node_id');

            foreach ($parents as $p) {
                $stack[] = $p;
            }
        }

        return false;
    }
}
