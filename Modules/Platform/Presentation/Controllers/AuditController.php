<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\PlatformAuditLog;

class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $entity = (string) $request->query('entity');

        return view('platform::audit.index', [
            'logs' => PlatformAuditLog::query()->with('admin')
                ->when($entity !== '', fn ($q) => $q->where('entity_type', $entity))
                ->latest('occurred_at')->latest('id')->paginate(50)->withQueryString(),
            'entity' => $entity,
        ]);
    }
}
