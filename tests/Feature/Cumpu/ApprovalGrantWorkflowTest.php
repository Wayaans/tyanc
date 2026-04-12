<?php

declare(strict_types=1);

use App\Jobs\ProcessUsersImport;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function approvalGrantWorkflowPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function approvalGrantWorkflowRole(string $name, int $level): Role
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

function approvalGrantWorkflowUser(Role $role, array $permissions): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->givePermissionTo(array_map(approvalGrantWorkflowPermission(...), $permissions));

    return $user;
}

it('advances a multi-step workflow to an approval grant and consumes it on retry', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalGrantWorkflowUser(approvalGrantWorkflowRole('Grant Workflow Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $stepOneRole = approvalGrantWorkflowRole('Grant Workflow Reviewer One', 50);
    $stepTwoRole = approvalGrantWorkflowRole('Grant Workflow Reviewer Two', 60);

    $stepOneReviewer = approvalGrantWorkflowUser($stepOneRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $stepTwoReviewer = approvalGrantWorkflowUser($stepTwoRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create([
            'workflow_type' => ApprovalRule::WorkflowMulti,
            'grant_validity_minutes' => 45,
        ]);

    $approvalRule->steps()->createMany([
        ['role_id' => $stepOneRole->id, 'step_order' => 1, 'label' => 'Department review'],
        ['role_id' => $stepTwoRole->id, 'step_order' => 2, 'label' => 'Final review'],
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'workflow-users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this workflow import.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('executed', false);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    $this->actingAs($stepOneReviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Step one approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusInReview)
        ->assertJsonPath('approval.current_step_order', 2);

    $this->actingAs($stepTwoReviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Final approval complete.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and($approvalRequest->fresh()->expires_at)->not->toBeNull();

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'workflow-users-retry.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('executed', true)
        ->assertJsonPath('approval', null)
        ->assertJsonPath('import.status', 'queued');

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed);
    Queue::assertPushed(ProcessUsersImport::class);
});

it('reassigns the active workflow step before the final approval grant is consumed', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalGrantWorkflowUser(approvalGrantWorkflowRole('Grant Reassign Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $approverRole = approvalGrantWorkflowRole('Grant Reassign Approver', 55);

    $firstApprover = approvalGrantWorkflowUser($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $secondApprover = approvalGrantWorkflowUser($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create([
            'grant_validity_minutes' => 30,
        ]);

    $step = $approvalRule->steps()->create([
        'role_id' => $approverRole->id,
        'step_order' => 1,
        'label' => 'Owned review',
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'reassign-users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this reassigned import.',
        ])
        ->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();
    $assignment = $approvalRequest->assignments()->where('assigned_to_id', $firstApprover->id)->firstOrFail();

    expect($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusPending)->count())->toBe(2);

    $this->actingAs($firstApprover)
        ->patchJson(route('cumpu.approvals.reassign', $approvalRequest), [
            'assignments' => [
                [
                    'assignment_id' => (string) $assignment->id,
                    'new_assignee_id' => (string) $secondApprover->id,
                ],
            ],
            'note' => 'Please take this one.',
        ])
        ->assertOk();

    $approvalRequest = $approvalRequest->fresh();

    expect($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusPending)->count())->toBe(1)
        ->and($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusPending)->firstOrFail()->assigned_to_id)->toBe($secondApprover->id);

    $this->actingAs($firstApprover)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'I should not be able to approve this now.',
        ])
        ->assertForbidden();

    $this->actingAs($secondApprover)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approving after reassignment.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'reassign-users-retry.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('executed', true)
        ->assertJsonPath('approval', null)
        ->assertJsonPath('import.status', 'queued');

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($approvalRequest->fresh()->last_reassigned_at)->not->toBeNull();
    Queue::assertPushed(ProcessUsersImport::class);
});
