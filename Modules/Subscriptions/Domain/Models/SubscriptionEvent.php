<?php

namespace Modules\Subscriptions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['subscription_id', 'type', 'data', 'admin_user_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'occurred_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
