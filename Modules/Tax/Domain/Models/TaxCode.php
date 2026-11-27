<?php

namespace Modules\Tax\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tax\Domain\Enums\TaxKind;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class TaxCode extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'company_id', 'code', 'kind', 'name_en', 'name_ar', 'is_active'];

    protected function casts(): array
    {
        return ['kind' => TaxKind::class, 'is_active' => 'boolean'];
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
