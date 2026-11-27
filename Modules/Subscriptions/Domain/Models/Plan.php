<?php

namespace Modules\Subscriptions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code', 'name_en', 'name_ar', 'description_en', 'description_ar', 'price_minor', 'currency',
        'interval', 'trial_days', 'max_users', 'max_companies', 'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'trial_days' => 'integer',
            'max_users' => 'integer',
            'max_companies' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function localizedDescription(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description_en;
    }

    public function isUnlimitedUsers(): bool
    {
        return $this->max_users === null;
    }

    public function isUnlimitedCompanies(): bool
    {
        return $this->max_companies === null;
    }
}
