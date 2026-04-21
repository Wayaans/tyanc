<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Illuminate\Support\Str;

final readonly class FlashToast
{
    public function __construct(
        public string $id,
        public string $variant,
        public string $message,
        public ?string $description = null,
    ) {}

    public static function success(string $message, ?string $description = null): self
    {
        return new self((string) Str::uuid(), 'success', $message, $description);
    }

    public static function info(string $message, ?string $description = null): self
    {
        return new self((string) Str::uuid(), 'info', $message, $description);
    }

    public static function warning(string $message, ?string $description = null): self
    {
        return new self((string) Str::uuid(), 'warning', $message, $description);
    }

    public static function error(string $message, ?string $description = null): self
    {
        return new self((string) Str::uuid(), 'error', $message, $description);
    }

    /**
     * @return array{id: string, variant: string, message: string, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'variant' => $this->variant,
            'message' => $this->message,
            'description' => $this->description,
        ];
    }
}
