<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;

trait BroadcastsDurableNotifications
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'read' => false,
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
