<?php

namespace Modules\Accounting\Domain\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'company_id', 'parent_id', 'code', 'name', 'name_ar',
        'type', 'is_postable', 'status', 'cash_flow_category',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
            'cash_flow_category' => CashFlowCategory::class,
            'is_postable' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar ? $this->name_ar : $this->name;
    }

    protected static function newFactory(): AccountFactory
    {
        return AccountFactory::new();
    }
}
