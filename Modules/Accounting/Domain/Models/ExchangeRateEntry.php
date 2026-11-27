<?php

namespace Modules\Accounting\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class ExchangeRateEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'exchange_rates';

    protected $fillable = ['tenant_id', 'company_id', 'currency_code', 'rate_date', 'rate', 'source', 'created_by'];

    protected function casts(): array
    {
        return ['rate_date' => 'date:Y-m-d'];
    }
}
