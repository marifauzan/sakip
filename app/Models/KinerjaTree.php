<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KinerjaTree extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'sector_id', 'name', 'period_start', 'period_end', 'status'];

    protected $casts = [
        'period_start' => 'integer',
        'period_end' => 'integer',
        'sector_id' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'tree_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(NodeLink::class, 'tree_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'tree_id');
    }
}
