<?php

declare(strict_types=1);

use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Permissions\PermissionKey;

it('tracks draft approvals against the saved draft subject revision', function (): void {
    $reviewerRole = Role::query()->create([
        'name' => 'Workflow Draft Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    $requester = User::factory()->create();
    $managedUser = User::factory()->create();

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->draftMode()
        ->enabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Draft review',
    ]);

    $draft = UserUpdateDraft::factory()->for($managedUser, 'user')->for($requester, 'creator')->create([
        'revision' => 7,
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->for($draft, 'subject')
        ->draftMode('7')
        ->create([
            'action' => PermissionKey::tyanc('users', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'update',
            'requested_by_id' => $requester->id,
            'mode' => ApprovalMode::Draft->value,
            'status' => ApprovalRequest::StatusApproved,
            'expires_at' => now()->addHour(),
        ]);

    expect($approvalRequest->subjectRevisionMatchesSubject())->toBeTrue()
        ->and($approvalRequest->isGrantConsumable())->toBeTrue();

    $draft->forceFill(['revision' => 8])->save();

    expect($approvalRequest->fresh()->subjectRevisionMatchesSubject())->toBeFalse()
        ->and($approvalRequest->fresh()->isGrantConsumable())->toBeFalse();
});
