<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AuditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
