<?php

namespace App\Http\Controllers;

use App\Exceptions\BillingException;
use App\Models\KashierOrder;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\KashierCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class KashierCheckoutController
{
    public function index(Request $request, KashierCheckoutService $checkout, EntitlementService $entitlements)
    {
        if (! config('kashier.web_enabled')) {
            return redirect()->route('download');
        }
        $this->locale($request);
        $user = $this->user($request);
        $orders = $user ? KashierOrder::query()->where('user_id', $user->id)->latest()->limit(10)->get() : collect();

        return response()->view('billing.index', [
            'user' => $user, 'plans' => $user ? $checkout->catalog($user) : [],
            'access' => $user ? $entitlements->snapshot($user)['access'] : null,
            'orders' => $orders, 'enabled' => $checkout->enabled(), 'mode' => config('kashier.mode'),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function login(Request $request)
    {
        abort_unless(config('kashier.web_enabled'), 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255']]);
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password) || $user->suspended_at) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect, or this account is unavailable.']);
        }
        if (config('auth.phone_verification_required') && $user->phone && ! $user->phone_verified_at) {
            throw ValidationException::withMessages(['email' => 'Verify your phone number in the AutoMind app before signing in here.']);
        }
        $request->session()->regenerate();
        $request->session()->put('kashier_user_id', $user->id);

        return redirect()->route('billing.index', ['lang' => $request->input('lang', 'en')]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('kashier_user_id');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('billing.index');
    }

    public function create(Request $request, KashierCheckoutService $checkout)
    {
        abort_unless(config('kashier.web_enabled'), 404);
        $this->locale($request);
        $user = $this->user($request);
        abort_unless($user !== null, 403);
        $data = $request->validate(['plan' => ['required', 'string', 'max:64'], 'idempotencyKey' => ['required', 'uuid']]);
        try {
            $order = $checkout->create($user, $data['plan'], $data['idempotencyKey']);
        } catch (BillingException $exception) {
            return back()->withErrors(['checkout' => $exception->getMessage()]);
        }
        if ($order->status === 'pending' && $order->expires_at->isFuture() && $order->checkout_url) {
            return redirect()->away($order->checkout_url);
        }

        return redirect()->route('billing.order', ['order' => $order->id, 'lang' => app()->getLocale()]);
    }

    public function show(Request $request, KashierOrder $order, KashierCheckoutService $checkout)
    {
        abort_unless(config('kashier.web_enabled'), 404);
        $this->locale($request);
        $user = $this->user($request);
        if (! $user) {
            return redirect()->route('billing.index', ['lang' => app()->getLocale()]);
        }
        abort_unless($order->user_id === $user->id, 404);
        $verificationPending = false;
        if (! $order->last_checked_at || $order->last_checked_at->lt(now()->subSeconds(10))) {
            try {
                $order = $checkout->refresh($order);
            } catch (BillingException) {
                $verificationPending = true;
            }
        }

        return response()->view('billing.order', compact('order', 'verificationPending'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function returned(Request $request, string $order)
    {
        if (! config('kashier.web_enabled')) {
            return response()->view('billing.return')->header('Cache-Control', 'no-store, private');
        }

        // Query parameters from Kashier are display/navigation hints only.
        return redirect()->route('billing.order', ['order' => $order]);
    }

    private function user(Request $request): ?User
    {
        $id = $request->session()->get('kashier_user_id');
        $user = is_string($id) ? User::query()->whereNull('suspended_at')->find($id) : null;
        if ($user && config('auth.phone_verification_required') && $user->phone && ! $user->phone_verified_at) {
            return null;
        }

        return $user;
    }

    private function locale(Request $request): void
    {
        app()->setLocale($request->input('lang') === 'ar' ? 'ar' : 'en');
    }
}
