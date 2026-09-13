<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    use HasFactory;

    protected $fillable = ['tree_id', 'code', 'statement', 'type', 'source_type', 'order'];

    public function tree(): BelongsTo
    {
        return $this->belongsTo(KinerjaTree::class, 'tree_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class);
    }

    /** Hubungan ke anak (node ini adalah parent). */
    public function childLinks(): HasMany
    {
        return $this->hasMany(NodeLink::class, 'parent_node_id');
    }

    /** Hubungan ke parent (node ini adalah child). */
    public function parentLinks(): HasMany
    {
        return $this->hasMany(NodeLink::class, 'child_node_id');
    }

    public function children(): Collection
    {
        return $this->childLinks()->with('child')->get()->map->child;
    }
}
