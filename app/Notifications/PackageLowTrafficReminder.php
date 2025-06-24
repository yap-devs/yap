<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
    )
    {
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
            ->subject('Notice: Low Data Remaining in Your Package')
            ->greeting('Dear ' . $this->user->name . ',')
            ->line('We would like to inform you that your current package is running low on available data.')
            ->line("You have approximately {$remainingGB} GB remaining out of your {$totalGB} GB package ({$percentRemaining}%).")
            ->line('To avoid any service disruption or additional charges, please consider purchasing additional data.')
            ->action('Purchase Additional Data', url('/package'))
            ->line('Thank you for choosing our services. If you need any assistance, please contact our support team.');
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
