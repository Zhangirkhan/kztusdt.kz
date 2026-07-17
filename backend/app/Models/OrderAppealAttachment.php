<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderAppealAttachment extends Model
{
    protected $fillable = [
        'order_appeal_id',
        'file_path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function orderAppeal(): BelongsTo
    {
        return $this->belongsTo(OrderAppeal::class);
    }
}
