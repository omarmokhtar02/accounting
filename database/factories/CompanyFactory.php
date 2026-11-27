<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Domain\Models\Tenant;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'country_code' => 'EG',
            'base_currency' => 'EGP',
            'status' => 'active',
        ];
    }

    public function saudi(): static
    {
        return $this->state(['country_code' => 'SA', 'base_currency' => 'SAR']);
    }

    public function kuwaiti(): static
    {
        return $this->state(['country_code' => 'KW', 'base_currency' => 'KWD']);
    }
}
