<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Identity\Application\RegisterTenant;
use Modules\Localization\Application\CountryRegistry;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(CountryRegistry $countries): View
    {
        return view('auth.register', ['countries' => $countries->all()]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, RegisterTenant $registerTenant, CountryRegistry $countries): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', Rule::in($countries->codes())],
        ]);

        $user = $registerTenant->handle(
            $request->name,
            $request->email,
            Hash::make($request->password),
            $request->company_name,
            $request->country_code,
            app()->getLocale(),
        );

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
