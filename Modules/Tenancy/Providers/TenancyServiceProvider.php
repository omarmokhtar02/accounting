<?php

namespace Modules\Tenancy\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Tenancy\Infrastructure\TenantContext;
use Modules\Tenancy\Presentation\CompanySwitcherComposer;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: reset between queued jobs and Octane requests so tenant state never leaks.
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        $this->registerTenantOwnedMacro();

        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');
        View::composer('layouts.topbar', CompanySwitcherComposer::class);
    }

    /**
     * $table->tenantOwned() adds tenant_id and company_id, the composite foreign key that forces a
     * row's tenant to match its company's tenant, and unique(company_id, id) so child tables can
     * reference the row together with its company.
     */
    private function registerTenantOwnedMacro(): void
    {
        if (Blueprint::hasMacro('tenantOwned')) {
            return;
        }

        Blueprint::macro('tenantOwned', function (bool $companyUnique = true): void {
            /** @var Blueprint $this */
            $this->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $this->unsignedBigInteger('company_id');
            $this->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->cascadeOnDelete();

            if ($companyUnique) {
                $this->unique(['company_id', 'id']);
            }
        });
    }
}
