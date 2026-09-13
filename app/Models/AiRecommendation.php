<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendation extends Model
{
    use HasFactory;

    public const KIND_CHILDREN = 'recommend_children';

    public const KIND_INDICATORS = 'recommend_indicators';

    public const KIND_SECTOR = 'detect_sector';

    protected $fillable = [
        'organization_id', 'tree_id', 'node_id', 'kind', 'model',
        'prompt_version', 'context_summary', 'output', 'decision',
    ];

    protected $casts = [
        'output' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tree(): BelongsTo
    {
        return $this->belongsTo(KinerjaTree::class, 'tree_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
