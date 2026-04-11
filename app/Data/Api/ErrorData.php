<?php

declare(strict_types=1);

namespace App\Data\Api;

use Spatie\LaravelData\Data;

final class ErrorData extends Data
{
    public function __construct(
        public int $status,
        public string $code,
        public string $message,
        public ?string $permission = null,
    ) {}

    public static function forbidden(?string $permission = null, ?string $message = null): self
    {
        return new self(
            status: 403,
            code: 'forbidden',
            message: $message ?? __('You do not have permission to access this resource.'),
            permission: $permission,
        );
    }
}
