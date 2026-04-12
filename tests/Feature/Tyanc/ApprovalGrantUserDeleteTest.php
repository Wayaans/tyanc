<?php

declare(strict_types=1);

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

function userDeleteGrantPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function userDeleteGrantRole(string $name, int $level): Role
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

function userDeleteGrantUser(array $permissions, ?Role $role = null): User
{
    $user = User::factory()->create();

    if ($role instanceof Role) {
        $user->assignRole($role);
    }

    $user->givePermissionTo(array_map(userDeleteGrantPermission(...), $permissions));

    return $user;
}

function userDeleteGrantRule(Role $reviewerRole): ApprovalRule
{
    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'delete'))
        ->enabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'User delete review',
    ]);

    return $approvalRule;
}

it('deletes users immediately when no approval rule is enabled', function (): void {
    $managerRole = userDeleteGrantRole('Direct Delete Managers', 50);

    $requester = userDeleteGrantUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    $managedUser = User::factory()->create([
        'name' => 'Delete Directly',
    ]);

    $this->actingAs($requester)
        ->deleteJson(route('tyanc.users.destroy', $managedUser))
        ->assertNoContent();

    expect(ApprovalRequest::query()->count())->toBe(0);
    $this->assertSoftDeleted($managedUser);
});

it('creates a delete approval request and consumes the grant on retry', function (): void {
    $managerRole = userDeleteGrantRole('Delete Request Managers', 50);
    $reviewerRole = userDeleteGrantRole('Delete Reviewers', 80);

    $requester = userDeleteGrantUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = userDeleteGrantUser([
        PermissionKey::tyanc('users', 'delete'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Delete Me Later',
    ]);

    userDeleteGrantRule($reviewerRole);

    $this->actingAs($requester)
        ->deleteJson(route('tyanc.users.destroy', $managedUser), [
            'request_note' => 'Please approve deleting this managed user.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('executed', false)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('approval.request_note', 'Please approve deleting this managed user.');

    expect($managedUser->fresh())->not->toBeNull();

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    expect($approvalRequest->subject_snapshot)
        ->toMatchArray([
            'id' => (string) $managedUser->id,
            'name' => 'Delete Me Later',
            'email' => $managedUser->email,
            'username' => $managedUser->username,
        ]);

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved for deletion.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->deleteJson(route('tyanc.users.destroy', $managedUser))
        ->assertNoContent();

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed);
    $this->assertSoftDeleted($managedUser);
});

it('rejects delete requests for reserved users before creating approval', function (): void {
    $managerRole = userDeleteGrantRole('Protected Delete Managers', 50);
    $reviewerRole = userDeleteGrantRole('Protected Delete Reviewers', 80);

    $requester = userDeleteGrantUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    userDeleteGrantUser([
        PermissionKey::tyanc('users', 'delete'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $reservedUser = User::factory()->create([
        'name' => 'Reserved Target',
        'is_reserved' => true,
        'reserved_key' => 'admin',
    ]);

    userDeleteGrantRule($reviewerRole);

    $this->actingAs($requester)
        ->deleteJson(route('tyanc.users.destroy', $reservedUser), [
            'request_note' => 'Please approve deleting this reserved user.',
        ])
        ->assertForbidden();

    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and($reservedUser->fresh())->not->toBeNull();
});
