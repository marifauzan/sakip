<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Indicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id', 'name', 'definition', 'unit', 'direction', 'data_source', 'baseline', 'target',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
