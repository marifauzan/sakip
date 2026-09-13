<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'name', 'slug', 'description'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function knowledgePacks(): HasMany
    {
        return $this->hasMany(KnowledgePack::class);
    }

    public function kinerjaTrees(): HasMany
    {
        return $this->hasMany(KinerjaTree::class);
    }
}
