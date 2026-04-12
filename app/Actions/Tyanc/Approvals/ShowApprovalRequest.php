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
            && (
                $access->handle($actor, PermissionKey::cumpu('my_requests', 'viewany'))
                || $access->handle($actor, PermissionKey::cumpu('my_requests', 'view'))
                || $access->handle($actor, PermissionKey::cumpu('approvals', 'view'))
            );
        $canViewInboxApproval = (
            $access->handle($actor, PermissionKey::cumpu('approval_inbox', 'viewany'))
            || $access->handle($actor, PermissionKey::cumpu('approval_inbox', 'view'))
        ) && $approvalRequest->assignments()->where('assigned_to_id', $actor->id)->exists();
        $canViewAnyApproval = $access->handle($actor, PermissionKey::cumpu('all_approvals', 'viewany'))
            || $access->handle($actor, PermissionKey::cumpu('all_approvals', 'view'))
            || $access->handle($actor, PermissionKey::cumpu('approvals', 'viewany'));

        throw_if(! $canViewOwnRequest && ! $canViewInboxApproval && ! $canViewAnyApproval, AuthorizationException::class);

        ApprovalRequest::expirePastDueGrants();

        return $approvalRequest->loadMissing([
            'requester',
            'reviewer',
            'cancelledBy',
            'consumedBy',
            'subject',
            'rule.steps.role',
            'assignments.assignee',
            'assignments.completedBy',
            'assignments.step.role',
        ]);
    }
}
