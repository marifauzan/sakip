<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait untuk multi-tenant: model apapun yang punya organization_id
 * wajib di-scope ke organisasi user yang sedang login.
 */
trait BelongsToOrganization
{
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
