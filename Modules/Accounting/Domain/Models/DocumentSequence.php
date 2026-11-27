<?php

namespace Modules\Accounting\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class DocumentSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'company_id', 'type', 'year', 'last_number'];
}
