<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
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
}
