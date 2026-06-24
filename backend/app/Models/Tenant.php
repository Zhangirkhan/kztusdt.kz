<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function defaultTenantId(): ?int
    {
        return self::query()->where('is_default', true)->value('id');
    }
}
