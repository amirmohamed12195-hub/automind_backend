<?php

namespace App\Jobs;

use App\Models\AiRun;
use App\Models\WebhookReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOpenAiWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $receiptId)
    {
        $this->onQueue('diagnostic-ai');
    }

    public function backoff(): array
    {
        return [random_int(8, 12), random_int(50, 70), random_int(270, 330), random_int(810, 990)];
    }

    public function handle(): void
    {
        $receipt = WebhookReceipt::query()->findOrFail($this->receiptId);
        if ($receipt->processed_at) {
            return;
        }
        if ($receipt->event_type === 'response.completed' && $receipt->provider_object_id) {
            AiRun::query()->where('provider_response_id', $receipt->provider_object_id)->where('status', 'running')->update(['status' => 'provider_completed', 'completed_at' => now()]);
        }
        $receipt->update(['status' => 'processed', 'processed_at' => now()]);
    }
}
