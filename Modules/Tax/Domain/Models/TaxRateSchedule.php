<?php

namespace Modules\Tax\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tax\Domain\Enums\TaxKind;
use Modules\Tax\Domain\ValueObjects\TaxRate;

/**
 * One effective-dated rate for a country's tax code. Global platform data (not tenant owned).
 */
class TaxRateSchedule extends Model
{
    protected $fillable = ['country_code', 'code', 'kind', 'name_en', 'name_ar', 'rate_bp', 'effective_from', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['kind' => TaxKind::class, 'rate_bp' => 'integer', 'effective_from' => 'date:Y-m-d'];
    }

    public function toRate(): TaxRate
    {
        return new TaxRate($this->rate_bp, $this->effective_from->toDateString());
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
