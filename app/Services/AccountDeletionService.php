<?php

namespace App\Services;

use App\Contracts\ObjectStorageProvider;
use App\Models\DeviceToken;
use App\Models\DiagnosticMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountDeletionService
{
    public function __construct(private readonly ObjectStorageProvider $storage) {}

    public function request(User $user): void
    {
        DB::transaction(function () use ($user): void {
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

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->forceDelete();
        });

        $storedFiles->unique(fn (array $file): string => implode(':', $file))->each(function (array $file): void {
            try {
                $this->storage->delete($file[0], $file[1]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
