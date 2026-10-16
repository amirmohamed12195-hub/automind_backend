<?php

namespace App\Jobs;

use App\Contracts\ObjectStorageProvider;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\DiagnosticMedia;
use App\Models\User;
use App\Models\WebhookReceipt;
use App\Services\AccountDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeExpiredData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $mediaCutoff = now()->subDays(config('automind.retention.raw_media_days'));
        DiagnosticMedia::query()->whereNull('deleted_at')->where('created_at', '<', $mediaCutoff)->whereHas('session', fn ($q) => $q->whereIn('status', ['completed', 'failed', 'cancelled']))->chunkById(100, function ($items): void {
            foreach ($items as $media) {
                app(ObjectStorageProvider::class)->delete($media->storage_disk, $media->storage_path);
                $media->update(['deleted_at' => now(), 'processing_status' => 'retention_deleted']);
            }
        });
        AiRun::query()->where('created_at', '<', now()->subDays(config('automind.retention.ai_metadata_days')))->update(['response_metadata_json' => null, 'raw_usage_json' => null]);
        AuditLog::query()->where('created_at', '<', now()->subDays(config('automind.retention.audit_days')))->delete();
        WebhookReceipt::query()->where('created_at', '<', now()->subDays(90))->delete();
        User::onlyTrashed()->where('deletion_requested_at', '<', now()->subDays(config('automind.retention.deleted_account_grace_days')))->each(fn (User $user) => app(AccountDeletionService::class)->purge($user));
    }
}
