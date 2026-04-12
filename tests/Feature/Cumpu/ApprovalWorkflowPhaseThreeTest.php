<?php

declare(strict_types=1);

use App\Jobs\ProcessUsersImport;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ImportRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalReassignedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

function phaseThreePermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function phaseThreeRole(string $name, int $level): Role
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

function phaseThreeUser(Role $role, array $permissions): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->givePermissionTo(array_map(phaseThreePermission(...), $permissions));

    return $user;
}

it('stores and updates multi-step approval rules through cumpu with audit entries', function (): void {
    $manager = phaseThreeUser(phaseThreeRole('Cumpu Rule Manager', 90), [
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'create'),
        PermissionKey::cumpu('approval_rules', 'update'),
    ]);

    $stepOneRole = phaseThreeRole('Phase Three Step One', 40);
    $stepTwoRole = phaseThreeRole('Phase Three Step Two', 60);

    $this->actingAs($manager)
        ->postJson(route('cumpu.approval-rules.store'), [
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'workflow_type' => ApprovalRule::WorkflowMulti,
            'enabled' => true,
            'grant_validity_minutes' => 90,
            'reminder_after_minutes' => 30,
            'escalation_after_minutes' => 60,
            'steps' => [
                ['role_id' => $stepOneRole->id, 'label' => 'Department review'],
                ['role_id' => $stepTwoRole->id, 'label' => 'Final review'],
            ],
        ])
        ->assertCreated();

    $approvalRule = ApprovalRule::query()
        ->where('permission_name', PermissionKey::tyanc('users', 'import'))
        ->firstOrFail();

    expect($approvalRule->workflow_type)->toBe(ApprovalRule::WorkflowMulti)
        ->and($approvalRule->grant_validity_minutes)->toBe(90)
        ->and($approvalRule->reminder_after_minutes)->toBe(30)
        ->and($approvalRule->escalation_after_minutes)->toBe(60)
        ->and($approvalRule->steps()->count())->toBe(2)
        ->and($approvalRule->steps()->orderBy('step_order')->pluck('label')->all())
        ->toBe(['Department review', 'Final review']);

    expect(Activity::query()->where('event', 'rule-created')->exists())->toBeTrue();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'enabled' => false,
            'grant_validity_minutes' => 180,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => 120,
            'steps' => [
                ['role_id' => $stepTwoRole->id, 'label' => 'Single final review'],
            ],
        ])
        ->assertOk();

    expect($approvalRule->fresh()->workflow_type)->toBe(ApprovalRule::WorkflowSingle)
        ->and($approvalRule->fresh()->enabled)->toBeFalse()
        ->and($approvalRule->fresh()->grant_validity_minutes)->toBe(180)
        ->and($approvalRule->fresh()->steps()->count())->toBe(1)
        ->and($approvalRule->fresh()->steps()->firstOrFail()->label)->toBe('Single final review');

    expect(Activity::query()->where('event', 'rule-updated')->exists())->toBeTrue();
});

it('advances multi-step approvals in order and completes the governed action only after the final approval', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = phaseThreeUser(phaseThreeRole('Phase Three Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $stepOneRole = phaseThreeRole('Phase Three Reviewer One', 50);
    $stepTwoRole = phaseThreeRole('Phase Three Reviewer Two', 60);

    $stepOneReviewer = phaseThreeUser($stepOneRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $stepTwoReviewer = phaseThreeUser($stepTwoRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create([
            'workflow_type' => ApprovalRule::WorkflowMulti,
        ]);

    $approvalRule->steps()->createMany([
        ['role_id' => $stepOneRole->id, 'step_order' => 1, 'label' => 'Department review'],
        ['role_id' => $stepTwoRole->id, 'step_order' => 2, 'label' => 'Final review'],
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'phase-three-users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this phased import.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('executed', false);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    expect($approvalRequest->assignments()->count())->toBe(1)
        ->and($approvalRequest->assignments()->firstOrFail()->assigned_to_id)->toBe($stepOneReviewer->id);

    $this->actingAs($stepTwoReviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Trying to approve too early.',
        ])
        ->assertForbidden();

    $this->actingAs($stepOneReviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Step one is approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusInReview)
        ->assertJsonPath('approval.current_step_order', 2);

    $approvalRequest = $approvalRequest->fresh(['assignments.step']);

    expect($approvalRequest->status)->toBe(ApprovalRequest::StatusInReview)
        ->and($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusPending)->count())->toBe(1)
        ->and($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusPending)->firstOrFail()->assigned_to_id)->toBe($stepTwoReviewer->id);

    $this->actingAs($stepTwoReviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Final approval complete.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and($approvalRequest->fresh()->expires_at)->not->toBeNull()
        ->and(ImportRun::query()->count())->toBe(0);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'phase-three-users-retry.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('executed', true)
        ->assertJsonPath('approval', null)
        ->assertJsonPath('import.status', ImportRun::StatusQueued);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed);
    Queue::assertPushed(ProcessUsersImport::class);
});

it('reassigns the active step to another eligible approver and blocks the previous assignee from approving', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Notification::fake();
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = phaseThreeUser(phaseThreeRole('Phase Three Reassign Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $approverRole = phaseThreeRole('Phase Three Reassign Approver', 55);

    $firstApprover = phaseThreeUser($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $secondApprover = phaseThreeUser($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();

    $step = $approvalRule->steps()->create([
        'role_id' => $approverRole->id,
        'step_order' => 1,
        'label' => 'Owned review',
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'phase-three-reassign.xlsx',
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

    expect($approvalRequest->last_reassigned_at)->not->toBeNull()
        ->and($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusPending)->count())->toBe(1)
        ->and($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusPending)->firstOrFail()->assigned_to_id)->toBe($secondApprover->id)
        ->and($approvalRequest->assignments()->where('status', ApprovalAssignment::StatusCancelled)->count())->toBe(1);

    Notification::assertSentTo(
        $secondApprover,
        ApprovalReassignedNotification::class,
        function (ApprovalReassignedNotification $notification) use ($secondApprover, $approvalRequest): bool {
            $payload = $notification->toArray($secondApprover);

            return data_get($payload, 'action_url') === route('cumpu.approvals.show', $approvalRequest, absolute: false)
                && str_contains((string) data_get($payload, 'body'), 'This request was reassigned to you.');
        },
    );

    $this->actingAs($firstApprover)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'I should no longer be able to approve this.',
        ])
        ->assertForbidden();

    $this->actingAs($secondApprover)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approving after reassignment.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and(Activity::query()->where('event', 'reassigned')->exists())->toBeTrue();
});
