<?php

namespace App\Services;

use App\Contracts\ObjectStorageProvider;
use App\Models\AppleAuthorization;
use App\Models\DeviceToken;
use App\Models\DiagnosticMedia;
use App\Models\User;
use App\Services\Auth\AppleAuthorizationService;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function __construct(private readonly ObjectStorageProvider $storage) {}

    public function request(User $user): void
    {
        $grantIds = AppleAuthorization::query()->where('user_id', $user->id)->pluck('id')->all();
        DB::transaction(function () use ($user): void {
            AppleAuthorization::query()->where('user_id', $user->id)->update(['user_id' => null, 'revocation_requested_at' => now(), 'retry_at' => null]);
            $user->tokens()->delete();
            DeviceToken::query()
                ->where('user_id', $user->id)
                ->update(['enabled' => false, 'updated_at' => now()]);
            DB::table('diagnostic_sessions')
                ->where('user_id', $user->id)
                ->whereIn('status', ['queued', 'analyzing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            $user->forceFill(['deletion_requested_at' => now()])->save();
            $user->delete();
        });
        // The scheduler retries provider failures without blocking account deletion.
        app(AppleAuthorizationService::class)->revokePending($grantIds);
    }

    public function purge(User $user): void
    {
        $storedFiles = DiagnosticMedia::query()
            ->whereHas('session', fn ($query) => $query->where('user_id', $user->id))
            ->get(['storage_disk', 'storage_path'])
            ->map(fn (DiagnosticMedia $media): array => [$media->storage_disk, $media->storage_path]);

        if ($user->avatar_path) {
            $storedFiles->push([(string) config('automind.media.disk'), $user->avatar_path]);
        }

        // Keep the database references until all storage deletions succeed, so a
        // failed object deletion remains retryable on the next retention run.
        $storedFiles->unique(fn (array $file): string => implode(':', $file))->each(function (array $file): void {
            $this->storage->delete($file[0], $file[1]);
        });

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->forceDelete();
        });

    }
}
