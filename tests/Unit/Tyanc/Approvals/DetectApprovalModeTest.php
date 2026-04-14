<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\DetectApprovalMode;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

function setDetectApprovalModeConfig(string $mode): void
{
    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'update' => [
                            'mode' => $mode,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                ['role' => 'Reviewers', 'label' => 'User update review'],
                            ],
                            'grant_validity_minutes' => 60,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                    ],
                ],
            ],
        ],
    ]);
}

function approvalReviewerRole(): Role
{
    return Role::query()->create([
        'name' => 'Reviewers',
        'guard_name' => 'web',
        'level' => 70,
    ]);
}

it('detects draft mode when a matching rule is enabled for a configured capability', function (): void {
    setDetectApprovalModeConfig(ApprovalMode::Draft->value);

    $actor = User::factory()->create();
    $reviewerRole = approvalReviewerRole();
    $target = User::factory()->create();

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->enabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'User update review',
    ]);

    $mode = resolve(DetectApprovalMode::class)->handle(
        actor: $actor,
        permissionName: PermissionKey::tyanc('users', 'update'),
        subject: $target,
    );

    expect($mode)->toBe(ApprovalMode::Draft);
});

it('returns none when the capability has no active rule', function (): void {
    setDetectApprovalModeConfig(ApprovalMode::Grant->value);

    $mode = resolve(DetectApprovalMode::class)->handle(
        actor: User::factory()->create(),
        permissionName: PermissionKey::tyanc('users', 'update'),
        subject: User::factory()->create(),
    );

    expect($mode)->toBe(ApprovalMode::None);
});

it('returns none for disabled rules', function (): void {
    setDetectApprovalModeConfig(ApprovalMode::Grant->value);

    $actor = User::factory()->create();
    $reviewerRole = approvalReviewerRole();
    $target = User::factory()->create();

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->disabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'User update review',
    ]);

    $mode = resolve(DetectApprovalMode::class)->handle(
        actor: $actor,
        permissionName: PermissionKey::tyanc('users', 'update'),
        subject: $target,
    );

    expect($mode)->toBe(ApprovalMode::None);
});

it('returns none for enabled rules that are missing runtime workflow configuration', function (): void {
    setDetectApprovalModeConfig(ApprovalMode::Grant->value);

    ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->enabled()
        ->managed(PermissionKey::tyanc('users', 'update'))
        ->create();

    $mode = resolve(DetectApprovalMode::class)->handle(
        actor: User::factory()->create(),
        permissionName: PermissionKey::tyanc('users', 'update'),
        subject: User::factory()->create(),
    );

    expect($mode)->toBe(ApprovalMode::None);
});
