<?php

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['admin_user_id', 'action', 'entity_type', 'entity_id', 'data', 'occurred_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'occurred_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
