<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Models\Account;
use Modules\Tenancy\Domain\Models\Company;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => Company::withoutGlobalScopes()->find($attributes['company_id'])->tenant_id,
            'company_id' => Company::factory(),
            'code' => (string) fake()->unique()->numberBetween(1000, 99999),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'is_postable' => true,
            'status' => 'active',
        ];
    }
}
