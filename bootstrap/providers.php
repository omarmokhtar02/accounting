<?php

use App\Providers\AppServiceProvider;
use Modules\Accounting\Providers\AccountingServiceProvider;
use Modules\Egypt\Providers\EgyptServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\Kuwait\Providers\KuwaitServiceProvider;
use Modules\Localization\Providers\LocalizationServiceProvider;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\SaudiArabia\Providers\SaudiArabiaServiceProvider;
use Modules\Shared\Providers\SharedServiceProvider;
use Modules\Subscriptions\Providers\SubscriptionsServiceProvider;
use Modules\Tax\Providers\TaxServiceProvider;
use Modules\Tenancy\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    TenancyServiceProvider::class,
    IdentityServiceProvider::class,
    AccountingServiceProvider::class,
    LocalizationServiceProvider::class,
    SubscriptionsServiceProvider::class,
    PlatformServiceProvider::class,
    TaxServiceProvider::class,

    // Country modules: one per country. To add a country, create its module and add one line here.
    EgyptServiceProvider::class,
    SaudiArabiaServiceProvider::class,
    KuwaitServiceProvider::class,
];
