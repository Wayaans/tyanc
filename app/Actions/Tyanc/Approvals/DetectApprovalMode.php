<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final readonly class DetectApprovalMode
{
    public function __construct(
        private ResolveApprovalCapability $capabilities,
        private ResolveApprovalRule $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(
        User $actor,
        string $permissionName,
        ?Model $subject = null,
        array $context = [],
    ): ApprovalMode {
        $capability = $this->capabilities->handle($permissionName);

        if (! $capability instanceof ApprovalCapabilityData) {
            return ApprovalMode::None;
        }

        return $this->rules->handle($actor, $permissionName, $subject, $context) instanceof ApprovalRule
            ? $capability->mode
            : ApprovalMode::None;
    }
}
