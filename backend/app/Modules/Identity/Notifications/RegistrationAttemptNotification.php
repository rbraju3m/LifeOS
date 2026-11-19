<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent instead of an error when someone registers with an email that already
 * has an account, so the registration endpoint does not reveal which
 * addresses are registered.
 */
class RegistrationAttemptNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject(__('identity.registration_attempt.subject'))
            ->line(__('identity.registration_attempt.line'))
            ->line(__('identity.registration_attempt.advice'))
            ->action(__('identity.registration_attempt.action'), config('lifeos.frontend_url').'/login');
    }
}
