<?php

arch('no debugging helpers are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch('domain rules do not depend on HTTP or the presentation layer')
    ->expect([
        'Modules\Accounting\Domain\Enums',
        'Modules\Accounting\Domain\Exceptions',
        'Modules\Accounting\Domain\Services',
        'Modules\Shared\Domain\Money',
        'Modules\Shared\Domain\Currency',
        'Modules\Shared\Domain\ExchangeRate',
        'Modules\Shared\Domain\Rounding',
    ])
    ->not->toUse(['Illuminate\Http', 'Illuminate\Support\Facades\Request', 'Illuminate\Support\Facades\Auth'])
    ->not->toUse(['Modules\Accounting\Presentation', 'Modules\Identity\Presentation']);

arch('application use cases do not depend on presentation')
    ->expect(['Modules\Accounting\Application', 'Modules\Identity\Application', 'Modules\Shared\Application'])
    ->not->toUse(['Illuminate\Http\Request', 'Modules\Accounting\Presentation', 'Modules\Identity\Presentation']);

arch('controllers stay thin and never touch the DB facade')
    ->expect(['Modules\Accounting\Presentation\Controllers', 'Modules\Identity\Presentation\Controllers'])
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('accounting does not reach into identity internals')
    ->expect('Modules\Accounting')
    ->not->toUse('Modules\Identity');

arch('tenant-owned models are tenant scoped')
    ->expect([
        'Modules\Accounting\Domain\Models\Account',
        'Modules\Accounting\Domain\Models\AccountingPeriod',
        'Modules\Accounting\Domain\Models\FiscalYear',
        'Modules\Accounting\Domain\Models\JournalEntry',
        'Modules\Accounting\Domain\Models\JournalLine',
        'Modules\Accounting\Domain\Models\DocumentSequence',
        'Modules\Shared\Domain\Models\AuditLog',
        'Modules\Tenancy\Domain\Models\Company',
    ])
    ->toUseTrait('Modules\Tenancy\Infrastructure\BelongsToTenant');

arch('strict types are not required but enums are backed enums')
    ->expect('Modules\Accounting\Domain\Enums')
    ->toBeEnums();

arch('core modules never depend on a country module')
    ->expect(['Modules\Accounting', 'Modules\Identity', 'Modules\Tenancy', 'Modules\Shared', 'Modules\Localization'])
    ->not->toUse(['Modules\Egypt', 'Modules\SaudiArabia', 'Modules\Kuwait']);

arch('country modules are independent of each other')
    ->expect('Modules\Egypt')->not->toUse(['Modules\SaudiArabia', 'Modules\Kuwait']);

arch('saudi module is independent of the other countries')
    ->expect('Modules\SaudiArabia')->not->toUse(['Modules\Egypt', 'Modules\Kuwait']);

arch('kuwait module is independent of the other countries')
    ->expect('Modules\Kuwait')->not->toUse(['Modules\Egypt', 'Modules\SaudiArabia']);

arch('country packs implement the country pack contract')
    ->expect(['Modules\Egypt\Domain\EgyptCountryPack', 'Modules\SaudiArabia\Domain\SaudiCountryPack', 'Modules\Kuwait\Domain\KuwaitCountryPack'])
    ->toImplement('Modules\Localization\Domain\Contracts\CountryPack');

arch('localization depends on accounting and tenancy only through their public classes')
    ->expect('Modules\Localization\Domain')
    ->not->toUse(['Illuminate\Http', 'Modules\Identity']);

arch('money value objects are immutable')
    ->expect(['Modules\Shared\Domain\Money', 'Modules\Shared\Domain\Currency', 'Modules\Shared\Domain\ExchangeRate'])
    ->toBeReadonly();
