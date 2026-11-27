<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Infrastructure\TenantTable;

covers(TenantTable::class);

it('is a no-op outside PostgreSQL', function () {
    if (DB::getDriverName() === 'pgsql') {
        $this->markTestSkipped('Covered by the Postgres suite.');
    }

    TenantTable::enableRowLevelSecurity('companies');
    TenantTable::disableRowLevelSecurity('companies');

    expect(Schema::hasTable('companies'))->toBeTrue();
});

it('enables forced row level security with a tenant policy on PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Row level security needs PostgreSQL.');
    }

    $rows = DB::select("select relrowsecurity, relforcerowsecurity from pg_class where relname = 'account_mappings'");

    expect($rows[0]->relrowsecurity)->toBeTrue()->and($rows[0]->relforcerowsecurity)->toBeTrue();
});

it('provides the tenantOwned() blueprint macro with the composite company foreign key', function () {
    expect(Blueprint::hasMacro('tenantOwned'))->toBeTrue();

    Schema::create('scratch_owned', function (Blueprint $table) {
        $table->id();
        $table->tenantOwned();
    });

    expect(Schema::hasColumns('scratch_owned', ['tenant_id', 'company_id']))->toBeTrue();

    $foreign = collect(Schema::getForeignKeys('scratch_owned'))->firstWhere('foreign_table', 'companies');

    expect($foreign['columns'])->toBe(['tenant_id', 'company_id'])->and($foreign['foreign_columns'])->toBe(['tenant_id', 'id']);

    Schema::drop('scratch_owned');
});
