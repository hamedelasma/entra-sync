<?php

declare(strict_types=1);

namespace O3\EntraSync\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use O3\EntraSync\Models\Concerns\HasEntraSync;

class User extends Authenticatable
{
    use HasEntraSync, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
