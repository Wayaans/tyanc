<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Data\Tyanc\Approvals\ApprovalCapabilityData;

final readonly class ResolveApprovalCapability
{
    public function __construct(private ListApprovalCapabilities $capabilities) {}

    public function handle(string $permissionName): ?ApprovalCapabilityData
    {
        /** @var ApprovalCapabilityData|null $capability */
        $capability = collect($this->capabilities->handle())
            ->first(fn (ApprovalCapabilityData $candidate): bool => $candidate->permission_name === $permissionName);

        return $capability;
    }
}
