<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageLowTrafficReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $remaining_traffic,
        public int $total_traffic,
        public float $remaining_percentage,
    ) {
        //
    }

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
        $remaining_gb = round($this->remaining_traffic / 1024 / 1024 / 1024, 2);
        $total_gb = round($this->total_traffic / 1024 / 1024 / 1024, 2);
        $percent_remaining = round($this->remaining_percentage * 100);

        return (new MailMessage)
            ->subject(__('messages.notifications.package_low_subject'))
            ->greeting(__('messages.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('messages.notifications.package_low_line_1'))
            ->line(__('messages.notifications.package_low_line_2', [
                'remaining' => $remaining_gb,
                'total' => $total_gb,
                'percent' => $percent_remaining,
            ]))
            ->line(__('messages.notifications.package_low_line_3'))
            ->action(__('messages.notifications.purchase_data'), url('/package'))
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
