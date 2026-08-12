<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetPageController
{
    public function show(Request $request): View
    {
        $locale = in_array($request->query('lang'), ['en', 'ar'], true) ? $request->query('lang') : 'en';

        return view('public.reset-password', [
            'locale' => $locale,
            'email' => (string) $request->query('email'),
            'token' => (string) $request->query('token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'lang' => ['nullable', 'in:en,ar'],
        ]);
        $locale = $data['lang'] ?? 'en';
        $credentials = collect($data)->only(['email', 'token', 'password', 'password_confirmation'])->all();
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => $locale === 'ar'
                    ? 'الرابط غير صالح أو انتهت صلاحيته. اطلب رابطاً جديداً من التطبيق.'
                    : 'This link is invalid or expired. Request a new one from the app.',
            ]);
        }

        return redirect()->route('password.reset.show', ['lang' => $locale])->with(
            'status',
            $locale === 'ar'
                ? 'تم تغيير كلمة المرور. يمكنك الآن العودة إلى التطبيق وتسجيل الدخول.'
                : 'Your password was changed. You can return to the app and sign in.',
        );
    }
}
