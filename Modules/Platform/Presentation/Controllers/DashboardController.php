<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Platform\Application\PlatformStats;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Domain\Money;

class DashboardController extends Controller
{
    public function __invoke(PlatformStats $stats, CurrencyRegistry $currencies): View
    {
        $snapshot = $stats->snapshot();

        // Format revenue per currency here so the view only prints strings.
        $snapshot['mrr_display'] = collect($snapshot['mrr'])
            ->map(fn (int $minor, string $code) => (new Money($minor, $currencies->get($code)))->format().' '.$code)
            ->values()->all();

        return view('platform::dashboard', ['stats' => $snapshot]);
    }
}
