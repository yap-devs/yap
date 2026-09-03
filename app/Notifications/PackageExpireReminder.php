<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageExpireReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('messages.notifications.package_expire_subject'))
            ->greeting(__('messages.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('messages.notifications.package_expire_line_1'))
            ->line(__('messages.notifications.package_expire_line_2'))
            ->action(__('messages.notifications.renew_now'), url('/package'))
            ->line(__('messages.notifications.thanks'));
    }

    /**
     * Calculate the number of seconds to wait before retrying the notification.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
