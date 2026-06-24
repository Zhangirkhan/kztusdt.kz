<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WalletCounter extends Model
{
    protected $fillable = [
        'network',
        'current_index',
    ];

    protected function casts(): array
    {
        return [
            'current_index' => 'integer',
        ];
    }
}
