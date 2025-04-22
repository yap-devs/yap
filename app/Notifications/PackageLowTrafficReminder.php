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
            ->subject('Low Package Data Remaining')
            ->greeting('Hello ' . $this->user->name . ',')
            ->line("We noticed that your package is running low on data.")
            ->line("You have approximately {$remainingGB} GB remaining out of your {$totalGB} GB package ({$percentRemaining}%).")
            ->line("Please consider purchasing additional data to avoid service interruption.")
            ->action('Purchase More Data', url('/package'))
            ->line('Thank you for being with us!');
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
