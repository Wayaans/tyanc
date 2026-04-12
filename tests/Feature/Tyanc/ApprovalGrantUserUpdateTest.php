<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;

function userUpdateGrantPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function userUpdateGrantRole(string $name, int $level): Role
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

function userUpdateGrantUser(array $permissions, ?Role $role = null): User
{
    $user = User::factory()->create();

    if ($role instanceof Role) {
        $user->assignRole($role);
    }

    $user->givePermissionTo(array_map(userUpdateGrantPermission(...), $permissions));

    return $user;
}

function userUpdateGrantRule(Role $reviewerRole): ApprovalRule
{
    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->enabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'User update review',
    ]);

    return $approvalRule;
}

/**
 * @return array<string, mixed>
 */
function userUpdateGrantPayload(Role $role, array $overrides = []): array
{
    return [
        ...[
            'name' => 'Approved User',
            'username' => 'approved-user',
            'email' => 'approved-user@example.com',
            'status' => UserStatus::Suspended->value,
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'roles' => [$role->name],
            'permissions' => [],
        ],
        ...$overrides,
    ];
}

it('updates users immediately when no approval rule is enabled', function (): void {
    $managerRole = userUpdateGrantRole('Direct User Managers', 50);
    $targetRole = userUpdateGrantRole('Direct Managed User', 10);

    $requester = userUpdateGrantUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    $managedUser = User::factory()->create([
        'name' => 'Original User',
        'username' => 'original-user',
        'email' => 'original-user@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), userUpdateGrantPayload($targetRole))
        ->assertOk()
        ->assertJsonMissingPath('approval')
        ->assertJsonPath('user.email', 'approved-user@example.com');

    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and($managedUser->fresh()->email)->toBe('approved-user@example.com')
        ->and($managedUser->fresh()->status)->toBe(UserStatus::Suspended);
});

it('captures the requester reason and subject snapshot when user update approval is required', function (): void {
    $managerRole = userUpdateGrantRole('Snapshot User Managers', 50);
    $reviewerRole = userUpdateGrantRole('Snapshot User Reviewers', 80);
    $targetRole = userUpdateGrantRole('Snapshot Managed User', 10);

    $requester = userUpdateGrantUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    userUpdateGrantUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Snapshot Target',
        'username' => 'snapshot-target',
        'email' => 'snapshot-target@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    userUpdateGrantRule($reviewerRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...userUpdateGrantPayload($targetRole),
            'request_note' => 'Please review this managed user update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('executed', false)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('approval.request_note', 'Please review this managed user update.');

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    expect($approvalRequest->requested_by_id)->toBe($requester->id)
        ->and($approvalRequest->action)->toBe(PermissionKey::tyanc('users', 'update'))
        ->and($approvalRequest->request_note)->toBe('Please review this managed user update.')
        ->and($approvalRequest->subject_snapshot)
        ->toMatchArray([
            'id' => (string) $managedUser->id,
            'name' => 'Snapshot Target',
            'email' => 'snapshot-target@example.com',
            'username' => 'snapshot-target',
            'status' => UserStatus::Active->value,
            'locale' => 'en',
            'timezone' => 'UTC',
            'roles' => [$targetRole->name],
            'permissions' => [],
        ])
        ->and($managedUser->fresh()->email)->toBe('snapshot-target@example.com');
});

it('blocks duplicate user update requests and consumes the approved grant on retry', function (): void {
    $managerRole = userUpdateGrantRole('Grant User Managers', 50);
    $reviewerRole = userUpdateGrantRole('Grant User Reviewers', 80);
    $targetRole = userUpdateGrantRole('Grant Managed User', 10);

    $requester = userUpdateGrantUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = userUpdateGrantUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Grant Target',
        'username' => 'grant-target',
        'email' => 'grant-target@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    userUpdateGrantRule($reviewerRole);

    $payload = userUpdateGrantPayload($targetRole, [
        'name' => 'Consumed User',
        'username' => 'consumed-user',
        'email' => 'consumed-user@example.com',
        'status' => UserStatus::Banned->value,
    ]);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...$payload,
            'request_note' => 'Please approve this update grant.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...$payload,
            'email' => 'duplicate@example.com',
            'request_note' => 'Please approve this duplicate update.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('approval');

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), $payload)
        ->assertOk()
        ->assertJsonPath('user.name', 'Consumed User')
        ->assertJsonPath('user.email', 'consumed-user@example.com');

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($managedUser->fresh()->name)->toBe('Consumed User')
        ->and($managedUser->fresh()->email)->toBe('consumed-user@example.com')
        ->and($managedUser->fresh()->status)->toBe(UserStatus::Banned);
});

it('rejects governed avatar changes while user update approval is required', function (): void {
    $managerRole = userUpdateGrantRole('Avatar User Managers', 50);
    $reviewerRole = userUpdateGrantRole('Avatar User Reviewers', 80);
    $targetRole = userUpdateGrantRole('Avatar Managed User', 10);

    $requester = userUpdateGrantUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    userUpdateGrantUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Avatar Target',
        'username' => 'avatar-target',
        'email' => 'avatar-target@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    userUpdateGrantRule($reviewerRole);

    $this->actingAs($requester)
        ->post(route('tyanc.users.update', $managedUser), [
            '_method' => 'PATCH',
            ...userUpdateGrantPayload($targetRole, [
                'name' => 'Avatar Target',
                'username' => 'avatar-target',
                'email' => 'avatar-target@example.com',
                'status' => UserStatus::Active->value,
                'locale' => 'en',
                'timezone' => 'UTC',
            ]),
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
            'request_note' => 'Please approve this avatar update.',
        ])
        ->assertSessionHasErrors('avatar');

    $managedUser->forceFill([
        'avatar' => 'avatars/current.png',
    ])->save();

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...userUpdateGrantPayload($targetRole, [
                'name' => 'Avatar Target',
                'username' => 'avatar-target',
                'email' => 'avatar-target@example.com',
                'status' => UserStatus::Active->value,
                'locale' => 'en',
                'timezone' => 'UTC',
            ]),
            'remove_avatar' => true,
            'request_note' => 'Please approve removing this avatar.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('remove_avatar');

    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and($managedUser->fresh()->avatar)->toBe('avatars/current.png');
});
