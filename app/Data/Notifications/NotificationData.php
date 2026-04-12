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
            action_url: self::normalizeActionUrl(is_string($payload['action_url'] ?? null) ? $payload['action_url'] : null),
            read: $notification->read_at !== null,
            read_at: $notification->read_at?->toIso8601String(),
            created_at: $notification->created_at instanceof CarbonInterface ? $notification->created_at->toIso8601String() : now()->toIso8601String(),
        );
    }

    private static function normalizeActionUrl(?string $actionUrl): ?string
    {
        if (! is_string($actionUrl) || mb_trim($actionUrl) === '') {
            return null;
        }

        $legacyPrefix = sprintf('/%s/approvals', mb_trim((string) config('tyanc.admin_path', 'tyanc'), '/'));
        $cumpuPrefix = route('cumpu.approvals.index', absolute: false);
        $parts = parse_url($actionUrl);

        if ($parts === false) {
            return $actionUrl;
        }

        $path = $parts['path'] ?? null;

        if (! is_string($path) || ! str_starts_with($path, $legacyPrefix)) {
            return $actionUrl;
        }

        $normalizedPath = $cumpuPrefix.mb_substr($path, mb_strlen($legacyPrefix));
        $suffix = isset($parts['query']) ? sprintf('?%s', $parts['query']) : '';
        $suffix .= isset($parts['fragment']) ? sprintf('#%s', $parts['fragment']) : '';

        if (! isset($parts['scheme'], $parts['host'])) {
            return $normalizedPath.$suffix;
        }

        $authority = sprintf('%s://%s', $parts['scheme'], $parts['host']);

        if (isset($parts['port'])) {
            $authority .= sprintf(':%s', $parts['port']);
        }

        return $authority.$normalizedPath.$suffix;
    }
}
