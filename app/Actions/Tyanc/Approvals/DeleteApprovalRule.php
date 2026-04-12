<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteApprovalRule
{
    public function handle(User $actor, ApprovalRule $approvalRule): void
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approval_rules', 'delete')),
            AuthorizationException::class,
        );

        DB::transaction(function () use ($actor, $approvalRule): void {
            activity('approvals')
                ->performedOn($approvalRule)
                ->causedBy($actor)
                ->event('rule-deleted')
                ->withProperties([
                    'attributes' => $approvalRule->load('steps.role')->toArray(),
                ])
                ->log('Approval rule deleted');

            $approvalRule->delete();
        });
    }
}
