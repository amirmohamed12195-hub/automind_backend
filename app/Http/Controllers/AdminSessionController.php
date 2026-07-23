<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSessionController
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->session()->get(config('admin.session_key')) === true) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $expectedUsername = (string) config('admin.username');
        $passwordHash = (string) config('admin.password_hash');
        $passwordInfo = password_get_info($passwordHash);
        $validHash = $passwordHash !== '' && $passwordInfo['algo'] !== null;
        $validUsername = $expectedUsername !== '' && hash_equals($expectedUsername, $credentials['username']);
        $validPassword = $validHash && password_verify($credentials['password'], $passwordHash);

        if (! $validUsername || ! $validPassword) {
            throw ValidationException::withMessages([
                'username' => 'The supplied administrator credentials are invalid.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put(config('admin.session_key'), true);
        $request->session()->put('automind_admin_username', $expectedUsername);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
