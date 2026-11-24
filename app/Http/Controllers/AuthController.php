<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (! User::where('is_admin', true)->exists()) {
            return redirect()->route('setup');
        }

        return view('auth', ['setup' => false]);
    }

    public function setup(): View
    {
        abort_if(DB::table('system_settings')->where('id', 1)->value('setup_complete'), 403, 'Administrator setup has already been completed.');

        return view('auth', ['setup' => true]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()]]);
        $user = DB::transaction(function () use ($data) {
            $settings = DB::table('system_settings')->where('id', 1)->lockForUpdate()->first();
            abort_if($settings->setup_complete, 403, 'Administrator setup has already been completed.');
            $user = new User($data);
            $user->is_admin = true;
            $user->save();
            DB::table('system_settings')->where('id', 1)->update(['setup_complete' => true]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt([...$data, 'is_admin' => true])) {
            throw ValidationException::withMessages(['email' => 'These administrator credentials do not match our records.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
