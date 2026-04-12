<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

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

it('lets a future app integrate approval grants through explicit governed action definitions', function (): void {
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

    $definition = function (?string $requestNote = null) use (&$executed, $workspace): array {
        return [
            'execute' => function () use (&$executed, $workspace): string {
                $executed[] = [
                    'subject_id' => (string) $workspace->id,
                    'publish_target' => 'catalog',
                ];

                return 'published';
            },
            'proposal' => [
                'request_note' => $requestNote,
                'payload' => [
                    'action_label' => 'Publish ERP workspace',
                    'subject_label' => $workspace->approvalSubjectLabel(),
                ],
                'subject_snapshot' => $workspace->approvalSubjectSnapshot(),
            ],
        ];
    };

    $submission = resolve(SubmitGovernedAction::class)->handle(
        actor: $requester,
        permissionName: 'erp.apps.publish',
        subject: $workspace,
        context: [
            'publish_target' => 'catalog',
        ],
        definition: $definition('Please publish the ERP workspace.'),
    );

    /** @var ApprovalRequest $approvalRequest */
    $approvalRequest = $submission['approval'];

    expect($submission['executed'])->toBeFalse()
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
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved)
        ->assertJsonPath('approval.subject_name', 'ERP Workspace');

    $consumption = resolve(SubmitGovernedAction::class)->handle(
        actor: $requester,
        permissionName: 'erp.apps.publish',
        subject: $workspace,
        context: [
            'publish_target' => 'catalog',
        ],
        definition: $definition(),
    );

    expect($consumption['executed'])->toBeTrue()
        ->and($consumption['result'])->toBe('published')
        ->and($consumption['approval'])->toBeNull()
        ->and($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($executed)
        ->toHaveCount(1)
        ->and($executed[0])
        ->toMatchArray([
            'subject_id' => (string) $workspace->id,
            'publish_target' => 'catalog',
        ]);
});
