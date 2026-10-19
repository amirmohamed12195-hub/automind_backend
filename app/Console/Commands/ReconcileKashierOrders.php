<?php

namespace App\Console\Commands;

use App\Exceptions\BillingException;
use App\Models\KashierOrder;
use App\Services\Billing\KashierCheckoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileKashierOrders extends Command
{
    protected $signature = 'automind:reconcile-kashier {--limit=50}';

    protected $description = 'Reconcile pending Kashier checkouts and recent payments without charging customers';

    public function handle(KashierCheckoutService $checkout): int
    {
        if (! config('kashier.api_key') || ! config('kashier.secret_key')) {
            return self::SUCCESS;
        }
        $orders = KashierOrder::query()->where('mode', config('kashier.mode'))->whereNotNull('session_id')
            ->whereIn('status', ['pending', 'paid', 'partially_refunded'])
            ->where('created_at', '>=', now()->subDays(180))
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(15)))
            ->orderBy('last_checked_at')->limit(max(1, min(100, (int) $this->option('limit'))))->get();
        $failed = 0;
        foreach ($orders as $order) {
            try {
                $checkout->refresh($order);
            } catch (BillingException $exception) {
                $failed++;
                // Back off failed orders so one bad session cannot starve the batch.
                $order->update(['last_checked_at' => now()]);
                Log::warning('kashier_reconciliation_failed', ['order_id' => $order->id, 'code' => $exception->errorCode]);
            }
        }
        $this->info('Kashier orders checked: '.$orders->count().'; failed: '.$failed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
