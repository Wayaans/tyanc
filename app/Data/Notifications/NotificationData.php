<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;

final class NotificationData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public string $kind,
        public string $title,
        public string $body,
        public ?string $action_label,
        public ?string $action_url,
        public bool $read,
        public ?string $read_at,
        public string $created_at,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        /** @var array<string, mixed> $payload */
        $payload = $notification->data;

        return new self(
            id: (string) $notification->id,
            type: (string) $notification->type,
            kind: (string) ($payload['kind'] ?? 'system'),
            title: (string) ($payload['title'] ?? __('Notification')),
            body: (string) ($payload['body'] ?? ''),
            action_label: is_string($payload['action_label'] ?? null) ? $payload['action_label'] : null,
            action_url: is_string($payload['action_url'] ?? null) ? $payload['action_url'] : null,
            read: $notification->read_at !== null,
            read_at: $notification->read_at?->toIso8601String(),
            created_at: $notification->created_at instanceof CarbonInterface ? $notification->created_at->toIso8601String() : now()->toIso8601String(),
        );
    }
}
