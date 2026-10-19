<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Billing\KashierCheckoutService;
use Illuminate\Http\Request;

class KashierWebhookController
{
    public function __invoke(Request $request, KashierCheckoutService $checkout)
    {
        abort_if(strlen($request->getContent()) > 65536, 413);
        $checkout->webhook($request->json()->all(), (string) $request->header('x-kashier-signature'));

        return response()->json(['received' => true]);
    }
}
