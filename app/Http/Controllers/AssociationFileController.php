<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AssociationFileController
{
    public function android(): JsonResponse
    {
        $fingerprints = config('public.app_links.android_sha256_fingerprints', []);
        abort_if($fingerprints === [], 503, 'Android app-link fingerprints are not configured.');

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('public.app_links.android_package'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]])->header('Cache-Control', 'public, max-age=3600');
    }

    public function apple(): JsonResponse
    {
        $teamId = trim((string) config('public.app_links.apple_team_id'));
        abort_if($teamId === '', 503, 'Apple Team ID is not configured.');

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appID' => $teamId.'.'.config('public.app_links.apple_bundle_id'),
                    'components' => [
                        ['/' => '/reset-password', 'comment' => 'Password-reset links'],
                        ['/' => '/reset-password/*', 'comment' => 'Password-reset child paths'],
                        ['/' => '/shared/reports/*', 'comment' => 'Signed shared diagnostic reports'],
                    ],
                ]],
            ],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
