<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use Illuminate\Validation\ValidationException;

final readonly class AuditLegacyApprovalRules
{
    /**
     * @param  array<int, ApprovalCapabilityData>  $capabilities
     * @return array{checked: int}
     */
    public function handle(array $capabilities): array
    {
        $capabilitiesByPermission = collect($capabilities)
            ->keyBy(fn (ApprovalCapabilityData $capability): string => $capability->permission_name);

        $checked = 0;

        ApprovalRule::query()
            ->get()
            ->each(function (ApprovalRule $approvalRule) use ($capabilitiesByPermission, &$checked): void {
                if ($approvalRule->managed_by_config) {
                    return;
                }

                /** @var ApprovalCapabilityData|null $capability */
                $capability = $capabilitiesByPermission->get((string) $approvalRule->permission_name);

                if (! $capability instanceof ApprovalCapabilityData) {
                    return;
                }

                $checked++;

                if (! $approvalRule->enabled) {
                    return;
                }

                $currentMode = $approvalRule->mode ?? ApprovalMode::Grant;

                if ($currentMode !== $capability->mode) {
                    throw ValidationException::withMessages([
                        'approval_rules' => __('Legacy approval rule for :permission uses :current mode, but config/approval-sot.php declares :configured. Sync after aligning the mode contract.', [
                            'permission' => $capability->permission_name,
                            'current' => $currentMode->value,
                            'configured' => $capability->mode->value,
                        ]),
                    ]);
                }
            });

        return ['checked' => $checked];
    }
}
