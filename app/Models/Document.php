<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'title', 'type', 'period_start', 'period_end',
        'status', 'file_path', 'file_name', 'file_mime', 'file_size',
        'version', 'sector_id', 'sector_confirmed_at', 'extract_error',
    ];

    protected $casts = [
        'period_start' => 'integer',
        'period_end' => 'integer',
        'file_size' => 'integer',
        'version' => 'integer',
        'sector_confirmed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
