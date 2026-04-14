<?php

declare(strict_types=1);

namespace App\Enums;

enum ApprovalMode: string
{
    case None = 'none';
    case Grant = 'grant';
    case Draft = 'draft';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $mode): string => $mode->value,
            self::cases(),
        );
    }

    public function requiresApproval(): bool
    {
        return $this !== self::None;
    }
}
