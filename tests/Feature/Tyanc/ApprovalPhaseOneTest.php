<?php

declare(strict_types=1);

use App\Jobs\ProcessUsersImport;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ImportRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalApprovedNotification;
use App\Notifications\NewApprovalRequestedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function approvalPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function approvalRole(string $name, int $level): Role
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

    $role->forceFill([
        'level' => $level,
    ])->save();

    return $role;
}

function approvalRequester(): User
{
    $requester = User::factory()->create();
    $requester->assignRole(approvalRole('Import Requester', 10));
    $requester->givePermissionTo([
        approvalPermission(PermissionKey::tyanc('users', 'import')),
        approvalPermission(PermissionKey::cumpu('approvals', 'view')),
    ]);

    return $requester;
}

function approvalReviewer(Role $role, array $permissions): User
{
    $reviewer = User::factory()->create();
    $reviewer->assignRole($role);
    $reviewer->givePermissionTo(array_map(approvalPermission(...), $permissions));

    return $reviewer;
}

it('submits import requests for approval and lists them in the cumpu inbox and my requests', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Notification::fake();

    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalRequester();
    $approverRole = approvalRole('Import Approver', 50);
    $reviewer = approvalReviewer($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $rule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();
    $rule->steps()->create([
        'role_id' => $approverRole->id,
        'step_order' => 1,
        'label' => 'Import approval',
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this import.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('executed', false)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('approval.subject_name', 'users.xlsx');

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    expect($approvalRequest->assignments()->where('assigned_to_id', $reviewer->id)->exists())->toBeTrue();

    Notification::assertSentTo(
        $reviewer,
        NewApprovalRequestedNotification::class,
        function (NewApprovalRequestedNotification $notification) use ($reviewer, $approvalRequest): bool {
            $payload = $notification->toArray($reviewer);

            return data_get($payload, 'action_url') === route('cumpu.approvals.show', $approvalRequest, absolute: false)
                && data_get($payload, 'approval_status') === ApprovalRequest::StatusPending
                && str_contains((string) data_get($payload, 'body'), 'retry Users import for users.xlsx once');
        },
    );

    $this->actingAs($reviewer)
        ->getJson(route('cumpu.approvals.index'))
        ->assertOk()
        ->assertJsonPath('approvalsTable.meta.total', 1)
        ->assertJsonPath('approvalsTable.rows.0.id', $approvalRequest->id);

    $this->actingAs($requester)
        ->getJson(route('cumpu.approvals.my-requests'))
        ->assertOk()
        ->assertJsonPath('approvalsTable.meta.total', 1)
        ->assertJsonPath('approvalsTable.rows.0.id', $approvalRequest->id);
});

it('redirects legacy tyanc approval workspace routes to cumpu', function (): void {
    $user = approvalRequester();

    $this->actingAs($user)
        ->get(route('tyanc.approvals.index'))
        ->assertRedirect(route('cumpu.approvals.index'));

    $this->actingAs($user)
        ->get(route('tyanc.approvals.my-requests'))
        ->assertRedirect(route('cumpu.approvals.my-requests'));
});

it('queues imports immediately when approval is not enabled for the action', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalRequester();
    $disabledRole = approvalRole('Disabled Rule Approver', 40);
    approvalReviewer($disabledRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $disabledRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->disabled()
        ->create();
    $disabledRule->steps()->create([
        'role_id' => $disabledRole->id,
        'step_order' => 1,
        'label' => 'Disabled rule',
    ]);

    Queue::fake();

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('executed', true)
        ->assertJsonPath('approval', null)
        ->assertJsonPath('import.status', 'queued');

    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and(ImportRun::query()->count())->toBe(1);

    Queue::assertPushed(ProcessUsersImport::class);
});

it('approves an import request and queues the import after approval', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Notification::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalRequester();
    $approverRole = approvalRole('Queue Approver', 60);
    $reviewer = approvalReviewer($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $rule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();
    $rule->steps()->create([
        'role_id' => $approverRole->id,
        'step_order' => 1,
        'label' => 'Queue approval',
    ]);

    $this->actingAs($requester)->postJson(route('tyanc.users.import.store'), [
        'file' => UploadedFile::fake()->create(
            'users.xlsx',
            32,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ),
        'request_note' => 'Please queue this import after approval.',
    ])->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    Queue::fake();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Looks good.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and($approvalRequest->fresh()->expires_at)->not->toBeNull()
        ->and(ImportRun::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('approvals/imports'))->toBe([]);

    Notification::assertSentTo(
        $requester,
        ApprovalApprovedNotification::class,
        function (ApprovalApprovedNotification $notification) use ($requester, $approvalRequest): bool {
            $payload = $notification->toArray($requester);

            return data_get($payload, 'action_url') === route('cumpu.approvals.show', $approvalRequest, absolute: false)
                && data_get($payload, 'approval_status') === ApprovalRequest::StatusApproved
                && data_get($payload, 'title') === 'Approval grant issued'
                && str_contains((string) data_get($payload, 'body'), 'Retry Users import for users.xlsx once before the grant expires.');
        },
    );

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'users-approved.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('executed', true)
        ->assertJsonPath('approval', null)
        ->assertJsonPath('import.status', ImportRun::StatusQueued);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and(ImportRun::query()->count())->toBe(1);

    Queue::assertPushed(ProcessUsersImport::class);
});

it('allows requesters to cancel pending approval requests without relying on staged files', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalRequester();
    $approverRole = approvalRole('Cancel Approver', 70);
    approvalReviewer($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'reject'),
    ]);

    $rule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();
    $rule->steps()->create([
        'role_id' => $approverRole->id,
        'step_order' => 1,
        'label' => 'Cancel approval',
    ]);

    $this->actingAs($requester)->postJson(route('tyanc.users.import.store'), [
        'file' => UploadedFile::fake()->create(
            'users.xlsx',
            32,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ),
        'request_note' => 'Please hold this import for review.',
    ])->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    expect(Storage::disk('local')->allFiles('approvals/imports'))->toBe([]);

    $this->actingAs($requester)
        ->patchJson(route('cumpu.approvals.cancel', $approvalRequest))
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusCancelled);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusCancelled)
        ->and(ImportRun::query()->count())->toBe(0);
});
