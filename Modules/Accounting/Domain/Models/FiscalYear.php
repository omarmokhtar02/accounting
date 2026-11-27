<?php

namespace Modules\Accounting\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class FiscalYear extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'company_id', 'name', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('number');
    }
}
