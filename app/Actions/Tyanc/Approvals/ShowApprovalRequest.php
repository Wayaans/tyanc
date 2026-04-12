<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ShowApprovalRequest
{
    public function handle(User $actor, ApprovalRequest $approvalRequest): ApprovalRequest
    {
        $access = resolve(PermissionResourceAccess::class);

        $canViewOwnRequest = $approvalRequest->requested_by_id === $actor->id
            && $access->handle($actor, PermissionKey::cumpu('approvals', 'view'));
        $canViewAnyApproval = $access->handle($actor, PermissionKey::cumpu('approvals', 'viewany'));

        throw_if(! $canViewOwnRequest && ! $canViewAnyApproval, AuthorizationException::class);

        return $approvalRequest->loadMissing([
            'requester',
            'reviewer',
            'cancelledBy',
            'subject',
            'rule.steps.role',
            'assignments.assignee',
            'assignments.completedBy',
            'assignments.step.role',
        ]);
    }
}
