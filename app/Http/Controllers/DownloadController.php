<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DownloadController
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $userAgent = strtolower($request->userAgent() ?? '');
        $storeUrl = null;

        // Windows Phone agents can also contain Android and iPhone identifiers.
        if (! str_contains($userAgent, 'windows phone')) {
            if (str_contains($userAgent, 'android')) {
                $storeUrl = config('public.play_store_url');
            } elseif (preg_match('/iphone|ipad|ipod|macintosh/', $userAgent)) {
                // iPads in desktop browsing mode also identify as Macintosh.
                $storeUrl = config('public.app_store_url');
            }
        }

        $response = $storeUrl
            ? redirect()->away($storeUrl, 302)
            : response()->view('public.download');

        // A shared cache must never reuse one device's redirect for another OS.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Vary', 'User-Agent');

        return $response;
    }
}
