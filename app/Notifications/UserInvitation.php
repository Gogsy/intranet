<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name') . ' — set your password')
            ->greeting('Hello ' . ($notifiable->name ?? '') . '!')
            ->line('An account has been created for you on ' . config('app.name') . '.')
            ->line('Click the button below to choose your password and activate your account.')
            ->action('Set your password', $url)
            ->line("This link expires in {$expire} minutes.")
            ->line('If you weren’t expecting this, you can safely ignore this email.');
    }
}
