<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\ExecuteApprovalControlledAction;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

function setExecuteApprovalControlledActionConfig(string $mode): void
{
    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => $mode,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                ['role' => 'Reviewers', 'label' => 'User delete review'],
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

it('short-circuits grant engine callers when the capability is in draft mode', function (): void {
    setExecuteApprovalControlledActionConfig(ApprovalMode::Draft->value);

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $reviewerRole = Role::query()->create([
        'name' => 'Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'delete'))
        ->enabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'User delete review',
    ]);

    $executed = false;

    $result = resolve(ExecuteApprovalControlledAction::class)->handle(
        actor: $actor,
        permissionName: PermissionKey::tyanc('users', 'delete'),
        subject: $target,
        definition: [
            'execute' => function () use (&$executed): string {
                $executed = true;

                return 'deleted';
            },
        ],
    );

    expect($result['mode'])->toBe(ApprovalMode::Draft->value)
        ->and($result['executed'])->toBeFalse()
        ->and($result['requires_draft_submission'])->toBeTrue()
        ->and($result['approval'])->toBeNull()
        ->and($executed)->toBeFalse();
});

it('executes immediately when approval mode resolves to none', function (): void {
    setExecuteApprovalControlledActionConfig(ApprovalMode::Grant->value);

    $executed = false;

    $result = resolve(ExecuteApprovalControlledAction::class)->handle(
        actor: User::factory()->create(),
        permissionName: PermissionKey::tyanc('users', 'delete'),
        subject: User::factory()->create(),
        definition: [
            'execute' => function () use (&$executed): string {
                $executed = true;

                return 'deleted';
            },
        ],
    );

    expect($result['mode'])->toBe(ApprovalMode::None->value)
        ->and($result['executed'])->toBeTrue()
        ->and($result['result'])->toBe('deleted')
        ->and($executed)->toBeTrue();
});
