<?php

namespace Tests\Feature;

use App\Contracts\PushNotificationProvider;
use App\Jobs\SendPushNotification;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Support\Facades\Queue;

class UserNotificationServiceTest extends ApiTestCase
{
    public function test_it_stores_in_app_notification_without_queuing_push_when_disabled(): void
    {
        Queue::fake();
        config()->set('automind.push_notifications_enabled', false);
        $user = $this->actingAsUser();

        $notification = app(UserNotificationService::class)->send(
            $user,
            'diagnosis_completed',
            'Report ready',
            'التقرير جاهز',
            'Open your report.',
            'افتح تقريرك.',
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => 'diagnosis_completed',
        ]);
        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_it_queues_push_when_enabled(): void
    {
        Queue::fake();
        config()->set('automind.push_notifications_enabled', true);
        $user = $this->actingAsUser();

        app(UserNotificationService::class)->send(
            $user,
            'diagnosis_completed',
            'Report ready',
            'التقرير جاهز',
            'Open your report.',
            'افتح تقريرك.',
        );

        Queue::assertPushed(SendPushNotification::class, 1);
    }

    public function test_already_queued_push_is_a_noop_when_notifications_are_disabled(): void
    {
        config()->set('automind.push_notifications_enabled', false);
        $provider = $this->createMock(PushNotificationProvider::class);
        $provider->expects($this->never())->method('send');

        (new SendPushNotification('user-id', 'Title', 'Body'))->handle($provider);
    }
}
