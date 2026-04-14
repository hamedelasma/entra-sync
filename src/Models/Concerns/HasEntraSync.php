<?php

declare(strict_types=1);

namespace HamedElasma\EntraSync\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasEntraSync
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFromEntra(Builder $query): Builder
    {
        return $query->whereNotNull('microsoft_id');
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function isEntraUser(): bool
    {
        return $this->microsoft_id !== null;
    }
}
