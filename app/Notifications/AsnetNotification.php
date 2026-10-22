<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AsnetNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly string $level = 'info',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'level' => $this->level,
        ];
    }
}
