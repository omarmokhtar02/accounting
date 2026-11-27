<?php

namespace Modules\Subscriptions\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Domain\Contracts\TrialStarter;
use Modules\Shared\Domain\Contracts\UsageLimits;
use Modules\Subscriptions\Application\StartTrial;
use Modules\Subscriptions\Infrastructure\PlanUsageLimits;
use Modules\Subscriptions\Presentation\Console\ExpireSubscriptionsCommand;
use Modules\Subscriptions\Presentation\SubscriptionBannerComposer;

class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrialStarter::class, StartTrial::class);
        $this->app->bind(UsageLimits::class, PlanUsageLimits::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Presentation/Views', 'subscriptions');

        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');
        View::composer('layouts.app', SubscriptionBannerComposer::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireSubscriptionsCommand::class]);
        }
    }
}
