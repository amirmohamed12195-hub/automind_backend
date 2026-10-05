<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmAccountDeletion extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $confirmationUrl,
        private readonly string $messageLocale = 'en',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->messageLocale === 'ar') {
            return (new MailMessage)
                ->subject('تأكيد حذف حساب أوتومايند')
                ->greeting('مرحباً،')
                ->line('وصلنا طلب لحذف حسابك وبياناتك في أوتومايند.')
                ->line('لن نحذف حسابك إلا بعد تأكيدك من الرابط التالي. الرابط صالح لمدة 60 دقيقة.')
                ->action('تأكيد حذف الحساب', $this->confirmationUrl)
                ->line('إذا لم تطلب حذف حسابك، تجاهل هذه الرسالة.');
        }

        return (new MailMessage)
            ->subject('Confirm your AutoMind account deletion')
            ->greeting('Hello,')
            ->line('We received a request to delete your AutoMind account and data.')
            ->line('Nothing will be deleted until you confirm below. This link expires in 60 minutes.')
            ->action('Confirm account deletion', $this->confirmationUrl)
            ->line('If you did not make this request, you can safely ignore this email.');
    }
}
