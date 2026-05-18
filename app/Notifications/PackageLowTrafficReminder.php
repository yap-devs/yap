<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageLowTrafficReminder extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public User $user,
        public UserPackage $userPackage,
        public float $remainingPercentage
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
        $remainingGB = round($this->userPackage->remaining_traffic / 1024 / 1024 / 1024, 2);
        $totalGB = round($this->userPackage->package->traffic_limit / 1024 / 1024 / 1024, 2);
        $percentRemaining = round($this->remainingPercentage * 100);

        return (new MailMessage)
            ->subject(__('messages.notifications.package_low_subject'))
            ->greeting(__('messages.notifications.greeting', ['name' => $this->user->name]))
            ->line(__('messages.notifications.package_low_line_1'))
            ->line(__('messages.notifications.package_low_line_2', [
                'remaining' => $remainingGB,
                'total' => $totalGB,
                'percent' => $percentRemaining,
            ]))
            ->line(__('messages.notifications.package_low_line_3'))
            ->action(__('messages.notifications.purchase_data'), url('/package'))
            ->line(__('messages.notifications.thanks'));
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
