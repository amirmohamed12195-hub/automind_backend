<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppleSignInCallbackController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'required_without:error', 'string', 'max:2048'],
            'id_token' => ['nullable', 'string', 'max:10000'],
            'state' => ['required', 'string', 'max:2048'],
            'user' => ['nullable', 'string', 'max:4096'],
            'error' => ['nullable', 'string', 'max:255'],
        ]);

        $query = http_build_query(
            array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== ''),
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        $package = (string) config('automind.apple_android_application_id');
        $intent = "intent://callback?$query#Intent;package=$package;scheme=signinwithapple;end";

        return redirect()->away($intent)->withHeaders([
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
