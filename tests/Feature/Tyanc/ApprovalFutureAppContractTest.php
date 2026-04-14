<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\ExecuteApprovalControlledAction;
use App\Enums\ApprovalMode;
use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppRegistrySeeder;

function futurePermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function futureRole(string $name, int $level): Role
{
    /** @var Role $role */
    $role = Role::query()->firstOrCreate(
        [
            'name' => $name,
            'guard_name' => 'web',
        ],
        [
            'level' => $level,
        ],
    );

    $role->forceFill(['level' => $level])->save();

    return $role;
}

function futureUser(array $permissions, ?Role $role = null): User
{
    $user = User::factory()->create();

    if ($role instanceof Role) {
        $user->assignRole($role);
    }

    $user->givePermissionTo(array_map(futurePermission(...), $permissions));

    return $user;
}

it('lets a future app integrate approval through the shared mode-aware gateway', function (): void {
    $this->seed(AppRegistrySeeder::class);

    config()->set('permission-sot.actions.publish', ['label' => 'Publish']);
    config()->set('permission-sot.manage_implies', array_values(array_unique([
        ...config('permission-sot.manage_implies', []),
        'publish',
    ])));
    config()->set('permission-sot.apps.erp', [
        'label' => 'ERP',
        'resources' => [
            'apps' => [
                'label' => 'Apps',
                'actions' => ['publish'],
            ],
        ],
    ]);
    config()->set('approval-sot.apps.erp', [
        'resources' => [
            'apps' => [
                'actions' => [
                    'publish' => [
                        'mode' => ApprovalMode::Grant->value,
                        'managed' => true,
                        'toggleable' => true,
                        'default_enabled' => false,
                        'workflow_type' => ApprovalRule::WorkflowSingle,
                        'steps' => [
                            ['role' => 'Future App Reviewers', 'label' => 'ERP publishing review'],
                        ],
                        'grant_validity_minutes' => 30,
                        'reminder_after_minutes' => null,
                        'escalation_after_minutes' => null,
                        'conditions' => null,
                    ],
                ],
            ],
        ],
    ]);

    $reviewerRole = futureRole('Future App Reviewers', 70);

    $requester = futureUser([
        'erp.apps.publish',
        'cumpu.approvals.view',
    ]);

    $reviewer = futureUser([
        'erp.apps.publish',
        'cumpu.approvals.viewany',
        'cumpu.approvals.approve',
    ], $reviewerRole);

    $approvalRule = ApprovalRule::factory()
        ->forPermission('erp.apps.publish')
        ->enabled()
        ->create([
            'grant_validity_minutes' => 30,
        ]);

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'ERP publishing review',
    ]);

    $workspace = App::factory()->create([
        'key' => 'erp',
        'label' => 'ERP Workspace',
        'route_prefix' => 'erp',
        'permission_namespace' => 'erp',
    ]);

    $executed = [];

    $submission = resolve(ExecuteApprovalControlledAction::class)->handle(
        actor: $requester,
        permissionName: 'erp.apps.publish',
        subject: $workspace,
        context: [
            'publish_target' => 'catalog',
        ],
        definition: [
            'execute' => function () use (&$executed, $workspace): string {
                $executed[] = [
                    'subject_id' => (string) $workspace->id,
                    'publish_target' => 'catalog',
                ];

                return 'published';
            },
            'proposal' => [
                'request_note' => 'Please publish the ERP workspace.',
                'payload' => [
                    'action_label' => 'Publish ERP workspace',
                    'subject_label' => $workspace->approvalSubjectLabel(),
                ],
                'subject_snapshot' => $workspace->approvalSubjectSnapshot(),
            ],
        ],
    );

    /** @var ApprovalRequest $approvalRequest */
    $approvalRequest = $submission['approval'];

    expect($submission['mode'])->toBe(ApprovalMode::Grant->value)
        ->and($submission['executed'])->toBeFalse()
        ->and($approvalRequest->request_note)->toBe('Please publish the ERP workspace.')
        ->and($approvalRequest->subject_snapshot)
        ->toMatchArray([
            'id' => (string) $workspace->id,
            'key' => 'erp',
            'label' => 'ERP Workspace',
            'permission_namespace' => 'erp',
        ]);

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved for publishing.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $consumption = resolve(ExecuteApprovalControlledAction::class)->handle(
        actor: $requester,
        permissionName: 'erp.apps.publish',
        subject: $workspace,
        context: [
            'publish_target' => 'catalog',
        ],
        definition: [
            'execute' => function () use (&$executed, $workspace): string {
                $executed[] = [
                    'subject_id' => (string) $workspace->id,
                    'publish_target' => 'catalog',
                ];

                return 'published';
            },
            'proposal' => [
                'payload' => [
                    'action_label' => 'Publish ERP workspace',
                    'subject_label' => $workspace->approvalSubjectLabel(),
                ],
                'subject_snapshot' => $workspace->approvalSubjectSnapshot(),
            ],
        ],
    );

    expect($consumption['mode'])->toBe(ApprovalMode::Grant->value)
        ->and($consumption['executed'])->toBeTrue()
        ->and($consumption['result'])->toBe('published')
        ->and($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($executed)->toHaveCount(1)
        ->and($executed[0])
        ->toMatchArray([
            'subject_id' => (string) $workspace->id,
            'publish_target' => 'catalog',
        ]);
});
