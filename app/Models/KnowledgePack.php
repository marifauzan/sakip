<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgePack extends Model
{
    use HasFactory;

    protected $fillable = ['sector_id', 'title', 'content', 'source', 'version', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
