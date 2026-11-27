<?php

namespace Modules\Shared\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class AuditLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'company_id', 'actor_id', 'entity_type', 'entity_id',
        'action', 'old_values', 'new_values', 'reason', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
