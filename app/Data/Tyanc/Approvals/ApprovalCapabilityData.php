<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Approvals;

use App\Enums\ApprovalMode;
use Spatie\LaravelData\Data;

final class ApprovalCapabilityData extends Data
{
    /**
     * @param  array<int, array{order: int, role_name: string, label: string|null}>  $steps
     * @param  array<string, mixed>|null  $conditions
     */
    public function __construct(
        public string $source_key,
        public string $permission_name,
        public string $app_key,
        public string $resource_key,
        public string $action_key,
        public ApprovalMode $mode,
        public bool $managed,
        public bool $toggleable,
        public bool $default_enabled,
        public string $workflow_type,
        public array $steps,
        public int $grant_validity_minutes,
        public ?int $reminder_after_minutes,
        public ?int $escalation_after_minutes,
        public ?array $conditions,
        public string $config_hash,
    ) {}
}
