<?php

namespace Modules\Accounting\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class AccountMapping extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'company_id', 'role', 'account_id'];

    protected function casts(): array
    {
        return ['role' => AccountRole::class];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
