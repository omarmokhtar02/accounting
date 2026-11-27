<?php

namespace Modules\Subscriptions\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Tenancy\Domain\Models\Tenant;

/**
 * Platform-level record (one tenant, one live subscription), so it is deliberately not scoped by the
 * tenant context: the platform admin reads across tenants.
 */
class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at',
        'canceled_at', 'price_minor', 'currency', 'interval', 'max_users', 'max_companies',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'price_minor' => 'integer',
            'max_users' => 'integer',
            'max_companies' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /** The moment access (before grace) ends: the trial end while trialing, the period end otherwise. */
    public function accessEndsAt(): ?CarbonInterface
    {
        return $this->status === SubscriptionStatus::Trialing ? $this->trial_ends_at : $this->current_period_ends_at;
    }

    /** Whole days left (0 once it has ended); null for an open-ended subscription. */
    public function daysLeft(?CarbonInterface $now = null): ?int
    {
        $end = $this->accessEndsAt();

        if ($end === null) {
            return null;
        }

        return max(0, (int) ceil(($now ?? now())->diffInSeconds($end, false) / 86400));
    }
}
