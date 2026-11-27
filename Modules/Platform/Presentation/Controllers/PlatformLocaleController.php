<?php

namespace Modules\Platform\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformLocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['en', 'ar'], true), 404);

        $request->session()->put('locale', $locale);

        if ($admin = $request->user('admin')) {
            $admin->update(['locale' => $locale]);
        }

        return back();
    }
}
