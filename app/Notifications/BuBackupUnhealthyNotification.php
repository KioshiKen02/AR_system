<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BuBackupUnhealthyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $runId,
        public readonly string $appName,
        public readonly string $environment,
        public readonly array $unhealthy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = "[{$this->environment}] {$this->appName} BU Backup Unhealthy ({$this->runId})";

        $mail = (new MailMessage())
            ->subject($subject)
            ->line("Run ID: {$this->runId}")
            ->line("Environment: {$this->environment}")
            ->line('Unhealthy Business Units:');

        foreach ($this->unhealthy as $entry) {
            $bu = $entry['bu'] ?? 'unknown';
            $buId = $entry['bu_id'] ?? 'unknown';
            $reason = $entry['reason'] ?? 'unknown';
            $mail->line("- {$bu} (bu_id={$buId}): {$reason}");
        }

        return $mail;
    }
}

