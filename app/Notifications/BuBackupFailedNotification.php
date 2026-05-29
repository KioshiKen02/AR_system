<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BuBackupFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $runId,
        public readonly string $appName,
        public readonly string $environment,
        public readonly array $failures,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = "[{$this->environment}] {$this->appName} BU Backup Failed ({$this->runId})";

        $mail = (new MailMessage())
            ->subject($subject)
            ->line("Run ID: {$this->runId}")
            ->line("Environment: {$this->environment}")
            ->line('Failed Business Units:');

        foreach ($this->failures as $failure) {
            $bu = $failure['bu'] ?? 'unknown';
            $buId = $failure['bu_id'] ?? 'unknown';
            $error = $failure['error'] ?? 'unknown error';
            $mail->line("- {$bu} (bu_id={$buId}): {$error}");
        }

        return $mail;
    }
}

