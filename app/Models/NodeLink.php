<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeLink extends Model
{
    use HasFactory;

    protected $fillable = ['tree_id', 'parent_node_id', 'child_node_id', 'reason'];

    public function tree(): BelongsTo
    {
        return $this->belongsTo(KinerjaTree::class, 'tree_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'parent_node_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'child_node_id');
    }
}
