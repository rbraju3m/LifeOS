<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $minutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.locked.subject'))
            ->line(__('identity.locked.line', ['minutes' => $this->minutes]))
            ->line(__('identity.locked.advice'))
            ->action(__('identity.locked.action'), config('lifeos.frontend_url').'/forgot-password');
    }
}
