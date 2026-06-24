<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class KycDocument extends Model
{
    protected $fillable = [
        'kyc_profile_id',
        'type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function kycProfile(): BelongsTo
    {
        return $this->belongsTo(KycProfile::class);
    }

    public function url(): string
    {
        return Storage::disk('local')->url($this->file_path);
    }
}
