<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes;
    use UsesTenantConnection;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'collection',
        'disk',
        'path',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size',
        'checksum',
        'uploaded_by_user_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
