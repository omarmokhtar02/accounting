<?php

namespace Modules\Tenancy\Presentation;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Supplies the navigation bar with the companies the user may switch to and the active one,
 * so the Blade view stays free of queries.
 */
final class CompanySwitcherComposer
{
    public function __construct(private readonly TenantContext $context) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        $view->with([
            'switcherCompanies' => $user && $this->context->tenantId() !== null ? $user->companies()->orderBy('name')->get() : collect(),
            'activeCompany' => $this->context->company(),
        ]);
    }
}
