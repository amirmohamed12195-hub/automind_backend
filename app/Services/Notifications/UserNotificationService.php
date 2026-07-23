<?php

namespace App\Services\Notifications;

use App\Jobs\SendPushNotification;
use App\Models\User;
use App\Models\UserNotification;

class UserNotificationService
{
    /** @param array<string, mixed> $data */
    public function send(
        User $user,
        string $type,
        string $titleEn,
        string $titleAr,
        string $bodyEn,
        string $bodyAr,
        array $data = [],
    ): UserNotification {
        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'body_en' => $bodyEn,
            'body_ar' => $bodyAr,
            'data_json' => $data === [] ? null : $data,
        ]);

        $arabic = $user->locale === 'ar';
        SendPushNotification::dispatch(
            (string) $user->id,
            $arabic ? $titleAr : $titleEn,
            $arabic ? $bodyAr : $bodyEn,
            [
                ...$data,
                'notificationId' => (string) $notification->id,
                'type' => $type,
            ],
        )->afterCommit();

        return $notification;
    }
}
