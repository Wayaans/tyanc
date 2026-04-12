<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Settings\AppearanceSettings;
use App\Settings\SecuritySettings;
use App\Settings\UserDefaultsSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Spatie\Activitylog\Models\Activity;

function governedPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function governedRole(string $name, int $level): Role
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

function governedUser(array $permissions, ?Role $role = null): User
{
    $user = User::factory()->create();

    if ($role instanceof Role) {
        $user->assignRole($role);
    }

    $user->givePermissionTo(array_map(governedPermission(...), $permissions));

    return $user;
}

function governedRule(string $permissionName, Role $reviewerRole): ApprovalRule
{
    $approvalRule = ApprovalRule::factory()
        ->forPermission($permissionName)
        ->enabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Approval review',
    ]);

    return $approvalRule;
}

it('exposes shared approval metadata through the approval subject concern', function (): void {
    $app = App::factory()->create([
        'key' => 'erp',
        'label' => 'ERP Workspace',
        'route_prefix' => 'erp',
        'permission_namespace' => 'erp',
        'enabled' => true,
    ]);

    $role = Role::query()->create([
        'name' => 'Operations Reviewer',
        'guard_name' => 'web',
        'level' => 40,
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->for($app, 'subject')
        ->create([
            'action' => PermissionKey::tyanc('apps', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'apps',
            'action_key' => 'update',
        ]);

    expect($app->approvalSubjectLabel())
        ->toBe('ERP Workspace')
        ->and($app->approvalSubjectSnapshot())
        ->toMatchArray([
            'id' => (string) $app->id,
            'key' => 'erp',
            'label' => 'ERP Workspace',
            'route_prefix' => 'erp',
            'permission_namespace' => 'erp',
            'enabled' => true,
        ])
        ->and($app->approvalRequests()->count())
        ->toBe(1)
        ->and($app->approvalHistory()->first()?->id)
        ->toBe($approvalRequest->id)
        ->and($role->approvalSubjectLabel())
        ->toBe('Operations Reviewer')
        ->and($role->approvalSubjectSnapshot())
        ->toMatchArray([
            'id' => (string) $role->id,
            'name' => 'Operations Reviewer',
            'guard_name' => 'web',
            'level' => 40,
        ]);
});

it('executes user updates immediately when no approval rule is enabled', function (): void {
    $managerRole = governedRole('Direct User Managers', 50);
    $targetRole = governedRole('Direct Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    $managedUser = User::factory()->create([
        'name' => 'Original Name',
        'username' => 'original-name',
        'email' => 'original@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            'name' => 'Directly Updated',
            'username' => 'directly-updated',
            'email' => 'direct@example.com',
            'status' => UserStatus::Suspended->value,
            'locale' => 'id',
            'timezone' => 'Asia/Jakarta',
            'roles' => [$targetRole->name],
            'permissions' => [],
        ])
        ->assertOk()
        ->assertJsonMissingPath('approval')
        ->assertJsonPath('user.name', 'Directly Updated');

    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and($managedUser->fresh()->email)->toBe('direct@example.com')
        ->and($managedUser->fresh()->status)->toBe(UserStatus::Suspended);
});

it('defers user updates through the governed action gateway and consumes the grant on retry', function (): void {
    $managerRole = governedRole('User Managers', 50);
    $reviewerRole = governedRole('User Update Reviewers', 80);
    $targetRole = governedRole('Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = governedUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Original Name',
        'username' => 'original-name',
        'email' => 'original@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    governedRule(PermissionKey::tyanc('users', 'update'), $reviewerRole);

    $payload = [
        'name' => 'Approved Name',
        'username' => 'approved-name',
        'email' => 'approved@example.com',
        'status' => UserStatus::Banned->value,
        'locale' => 'id',
        'timezone' => 'Asia/Makassar',
        'roles' => [$targetRole->name],
        'permissions' => [],
    ];

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...$payload,
            'request_note' => 'Please review this user update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('executed', false)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('approval.subject_name', 'Original Name')
        ->assertJsonPath('approval.request_note', 'Please review this user update.');

    $managedUser->refresh();

    expect($managedUser->name)->toBe('Original Name')
        ->and($managedUser->email)->toBe('original@example.com')
        ->and($managedUser->status)->toBe(UserStatus::Active);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and($approvalRequest->fresh()->expires_at)->not->toBeNull();

    $managedUser->refresh();

    expect($managedUser->name)->toBe('Original Name')
        ->and($managedUser->email)->toBe('original@example.com')
        ->and($managedUser->status)->toBe(UserStatus::Active);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), $payload)
        ->assertOk()
        ->assertJsonPath('user.name', 'Approved Name')
        ->assertJsonPath('user.email', 'approved@example.com');

    $managedUser->refresh();

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($managedUser->name)->toBe('Approved Name')
        ->and($managedUser->username)->toBe('approved-name')
        ->and($managedUser->email)->toBe('approved@example.com')
        ->and($managedUser->status)->toBe(UserStatus::Banned)
        ->and($managedUser->locale)->toBe('id')
        ->and($managedUser->timezone)->toBe('Asia/Makassar');
});

it('bypasses approval when the requester already qualifies as the reviewer', function (): void {
    $approverRole = governedRole('Immediate Approvers', 80);
    $targetRole = governedRole('Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $approverRole);

    $managedUser = User::factory()->create([
        'status' => UserStatus::Active,
    ]);
    $managedUser->assignRole($targetRole);

    governedRule(PermissionKey::tyanc('users', 'suspend'), $approverRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.suspend', $managedUser), [
            'request_note' => 'Suspend immediately.',
        ])
        ->assertOk()
        ->assertJsonPath('user.status', UserStatus::Suspended->value);

    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and($managedUser->fresh()->status)->toBe(UserStatus::Suspended)
        ->and(Activity::query()->where('log_name', 'approvals')->where('event', 'bypassed')->exists())
        ->toBeTrue();
});

it('defers user suspension through the governed action gateway and consumes the grant on retry', function (): void {
    $managerRole = governedRole('Suspend User Managers', 50);
    $reviewerRole = governedRole('Suspend User Reviewers', 80);
    $targetRole = governedRole('Suspend Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = governedUser([
        PermissionKey::tyanc('users', 'suspend'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Suspend Me',
        'status' => UserStatus::Active,
    ]);
    $managedUser->assignRole($targetRole);

    governedRule(PermissionKey::tyanc('users', 'suspend'), $reviewerRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.suspend', $managedUser), [
            'request_note' => 'Please suspend this user.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('approval.subject_name', 'Suspend Me');

    expect($managedUser->fresh()->status)->toBe(UserStatus::Active);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved for suspension.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.suspend', $managedUser))
        ->assertOk()
        ->assertJsonPath('user.status', UserStatus::Suspended->value);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($managedUser->fresh()->status)->toBe(UserStatus::Suspended);
});

it('uses actual changed fields when resolving governed user update rules', function (): void {
    $managerRole = governedRole('Conditional User Managers', 50);
    $reviewerRole = governedRole('Conditional User Reviewers', 80);
    $targetRole = governedRole('Conditional Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    governedUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    $approvalRule = governedRule(PermissionKey::tyanc('users', 'update'), $reviewerRole);
    $approvalRule->forceFill([
        'conditions' => [
            'changed_fields' => ['status'],
        ],
    ])->save();

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            'name' => $managedUser->name,
            'username' => $managedUser->username,
            'email' => $managedUser->email,
            'status' => UserStatus::Active->value,
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'roles' => [$targetRole->name],
            'permissions' => [],
        ])
        ->assertOk()
        ->assertJsonMissingPath('approval')
        ->assertJsonPath('user.locale', 'id');

    expect(ApprovalRequest::query()->count())->toBe(0);
});

it('blocks duplicate pending governed requests for the same user action', function (): void {
    $managerRole = governedRole('User Managers', 50);
    $reviewerRole = governedRole('User Update Reviewers', 80);
    $targetRole = governedRole('Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);
    governedUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    governedRule(PermissionKey::tyanc('users', 'update'), $reviewerRole);

    $payload = [
        'name' => 'Pending Update',
        'username' => 'pending-update',
        'email' => 'pending@example.com',
        'status' => UserStatus::Active->value,
        'locale' => 'en',
        'timezone' => 'UTC',
        'roles' => [$targetRole->name],
        'permissions' => [],
    ];

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...$payload,
            'request_note' => 'Please review this pending update.',
        ])
        ->assertStatus(202);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...$payload,
            'email' => 'second@example.com',
            'request_note' => 'Please review this duplicate update.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('approval');
});

it('keeps approval grants bound to the original requester', function (): void {
    $managerRole = governedRole('Grant User Managers', 50);
    $reviewerRole = governedRole('Grant Reviewers', 80);
    $targetRole = governedRole('Grant Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $otherRequester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = governedUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'email' => 'before-grant@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    governedRule(PermissionKey::tyanc('users', 'update'), $reviewerRole);

    $payload = [
        'name' => $managedUser->name,
        'username' => $managedUser->username,
        'email' => 'approved-grant@example.com',
        'status' => UserStatus::Active->value,
        'locale' => 'en',
        'timezone' => 'UTC',
        'roles' => [$targetRole->name],
        'permissions' => [],
    ];

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...$payload,
            'request_note' => 'Please approve this requester-bound grant.',
        ])
        ->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Grant approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($otherRequester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            ...$payload,
            'request_note' => 'Please review this separate requester attempt.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and(ApprovalRequest::query()->count())->toBe(2)
        ->and($managedUser->fresh()->email)->toBe('before-grant@example.com');
});

it('keeps approval grants bound to the approved subject record', function (): void {
    $managerRole = governedRole('App Grant Managers', 50);
    $reviewerRole = governedRole('App Grant Reviewers', 80);

    $requester = governedUser([
        PermissionKey::tyanc('apps', 'manage'),
    ], $managerRole);

    $reviewer = governedUser([
        PermissionKey::tyanc('apps', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $approvedApp = App::factory()->create([
        'key' => 'approved-app',
        'label' => 'Approved App',
        'route_prefix' => 'approved-app',
        'icon' => 'layout-grid',
        'permission_namespace' => 'approved_app',
    ]);

    $otherApp = App::factory()->create([
        'key' => 'other-app',
        'label' => 'Other App',
        'route_prefix' => 'other-app',
        'icon' => 'layout-grid',
        'permission_namespace' => 'other_app',
    ]);

    governedRule(PermissionKey::tyanc('apps', 'update'), $reviewerRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.apps.update', $approvedApp), [
            'key' => 'approved-app',
            'label' => 'Approved App Updated',
            'route_prefix' => 'approved-app',
            'icon' => 'layout-grid',
            'permission_namespace' => 'approved_app',
            'enabled' => true,
            'sort_order' => 0,
            'request_note' => 'Please approve this app grant.',
        ])
        ->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Grant approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.apps.update', $otherApp), [
            'key' => 'other-app',
            'label' => 'Other App Updated',
            'route_prefix' => 'other-app',
            'icon' => 'layout-grid',
            'permission_namespace' => 'other_app',
            'enabled' => true,
            'sort_order' => 10,
            'request_note' => 'Please approve this different app update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and(ApprovalRequest::query()->count())->toBe(2)
        ->and($otherApp->fresh()->label)->toBe('Other App');
});

it('defers role, app, and security setting updates when matching rules are enabled', function (): void {
    $managerRole = governedRole('Platform Managers', 50);
    $reviewerRole = governedRole('Platform Reviewers', 80);

    $requester = governedUser([
        PermissionKey::tyanc('roles', 'manage'),
        PermissionKey::tyanc('apps', 'manage'),
        PermissionKey::tyanc('settings', 'manage'),
    ], $managerRole);

    governedUser([
        PermissionKey::tyanc('roles', 'update'),
        PermissionKey::tyanc('apps', 'update'),
        PermissionKey::tyanc('settings', 'manage'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $role = Role::query()->create([
        'name' => 'Analyst',
        'guard_name' => 'web',
        'level' => 5,
    ]);

    $app = App::factory()->create([
        'key' => 'tasks',
        'label' => 'Tasks',
        'route_prefix' => 'tasks',
        'permission_namespace' => 'tasks',
        'enabled' => true,
    ]);

    governedRule(PermissionKey::tyanc('roles', 'update'), $reviewerRole);
    governedRule(PermissionKey::tyanc('apps', 'update'), $reviewerRole);
    governedRule(PermissionKey::tyanc('settings', 'update'), $reviewerRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.roles.update', $role), [
            'name' => 'Senior Analyst',
            'level' => 7,
            'request_note' => 'Please review this role change.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('roles', 'update'));

    $this->actingAs($requester)
        ->patchJson(route('tyanc.apps.update', $app), [
            'key' => 'tasks',
            'label' => 'Tasks Workspace',
            'route_prefix' => 'tasks',
            'icon' => 'layout-grid',
            'permission_namespace' => 'tasks',
            'enabled' => true,
            'sort_order' => 10,
            'request_note' => 'Please review this app update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('apps', 'update'));

    $beforeEnforceTwoFactor = resolve(SecuritySettings::class)->enforce_2fa;

    $this->actingAs($requester)
        ->patchJson(route('tyanc.settings.security.update'), [
            'enforce_2fa' => ! $beforeEnforceTwoFactor,
            'session_timeout' => 180,
            'request_note' => 'Please review this security update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('settings', 'update'));

    expect($role->fresh()->name)->toBe('Analyst')
        ->and($app->fresh()->label)->toBe('Tasks')
        ->and(resolve(SecuritySettings::class)->session_timeout)->toBe((int) config('session.lifetime'))
        ->and(ApprovalRequest::query()->count())->toBe(3);
});

it('consumes grants for role and app updates on retry', function (): void {
    $managerRole = governedRole('Platform Grant Managers', 50);
    $reviewerRole = governedRole('Platform Grant Reviewers', 80);

    $requester = governedUser([
        PermissionKey::tyanc('roles', 'manage'),
        PermissionKey::tyanc('apps', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = governedUser([
        PermissionKey::tyanc('roles', 'update'),
        PermissionKey::tyanc('apps', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $role = Role::query()->create([
        'name' => 'Operator',
        'guard_name' => 'web',
        'level' => 5,
    ]);

    $app = App::factory()->create([
        'key' => 'erp',
        'label' => 'ERP',
        'route_prefix' => 'erp',
        'icon' => 'layout-grid',
        'permission_namespace' => 'erp',
        'enabled' => true,
        'sort_order' => 0,
    ]);

    governedRule(PermissionKey::tyanc('roles', 'update'), $reviewerRole);
    governedRule(PermissionKey::tyanc('apps', 'update'), $reviewerRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.roles.update', $role), [
            'name' => 'Senior Operator',
            'level' => 7,
            'request_note' => 'Please approve this role update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('roles', 'update'));

    $roleApprovalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $roleApprovalRequest), [
            'review_note' => 'Approved role update.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.roles.update', $role), [
            'name' => 'Senior Operator',
            'level' => 7,
        ])
        ->assertOk()
        ->assertJsonPath('role.name', 'Senior Operator')
        ->assertJsonPath('role.level', 7);

    expect($roleApprovalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($role->fresh()->name)->toBe('Senior Operator')
        ->and((int) $role->fresh()->level)->toBe(7);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.apps.update', $app), [
            'key' => 'erp',
            'label' => 'ERP Workspace',
            'route_prefix' => 'erp',
            'icon' => 'layout-grid',
            'permission_namespace' => 'erp',
            'enabled' => true,
            'sort_order' => 10,
            'request_note' => 'Please approve this app update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('apps', 'update'));

    $appApprovalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $appApprovalRequest), [
            'review_note' => 'Approved app update.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.apps.update', $app), [
            'key' => 'erp',
            'label' => 'ERP Workspace',
            'route_prefix' => 'erp',
            'icon' => 'layout-grid',
            'permission_namespace' => 'erp',
            'enabled' => true,
            'sort_order' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('app.label', 'ERP Workspace')
        ->assertJsonPath('app.sort_order', 10);

    expect($appApprovalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($app->fresh()->label)->toBe('ERP Workspace')
        ->and((int) $app->fresh()->sort_order)->toBe(10);
});

it('consumes grants for security, appearance, and user default settings updates on retry', function (): void {
    $managerRole = governedRole('Settings Grant Managers', 50);
    $reviewerRole = governedRole('Settings Grant Reviewers', 80);

    $requester = governedUser([
        PermissionKey::tyanc('settings', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = governedUser([
        PermissionKey::tyanc('settings', 'manage'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    governedRule(PermissionKey::tyanc('settings', 'update'), $reviewerRole);

    $beforeEnforceTwoFactor = resolve(SecuritySettings::class)->enforce_2fa;

    $this->actingAs($requester)
        ->patchJson(route('tyanc.settings.security.update'), [
            'enforce_2fa' => ! $beforeEnforceTwoFactor,
            'session_timeout' => 180,
            'request_note' => 'Please approve this security update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('settings', 'update'));

    $securityApprovalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $securityApprovalRequest), [
            'review_note' => 'Approved security update.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.settings.security.update'), [
            'enforce_2fa' => ! $beforeEnforceTwoFactor,
            'session_timeout' => 180,
        ])
        ->assertOk()
        ->assertJsonPath('settings.enforce_2fa', ! $beforeEnforceTwoFactor)
        ->assertJsonPath('settings.session_timeout', 180);

    expect($securityApprovalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and(resolve(SecuritySettings::class)->enforce_2fa)->toBe(! $beforeEnforceTwoFactor)
        ->and(resolve(SecuritySettings::class)->session_timeout)->toBe(180);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.settings.appearance.update'), [
            'primary_color' => 'oklch(0.45 0.18 210)',
            'secondary_color' => 'oklch(0.94 0 0)',
            'border_radius' => '1rem',
            'spacing_density' => 'comfortable',
            'font_family' => 'instrument-sans',
            'sidebar_variant' => 'floating',
            'request_note' => 'Please approve this appearance update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('settings', 'update'));

    $appearanceApprovalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $appearanceApprovalRequest), [
            'review_note' => 'Approved appearance update.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.settings.appearance.update'), [
            'primary_color' => 'oklch(0.45 0.18 210)',
            'secondary_color' => 'oklch(0.94 0 0)',
            'border_radius' => '1rem',
            'spacing_density' => 'comfortable',
            'font_family' => 'instrument-sans',
            'sidebar_variant' => 'floating',
        ])
        ->assertOk()
        ->assertJsonPath('settings.spacing_density', 'comfortable')
        ->assertJsonPath('settings.sidebar_variant', 'floating');

    expect($appearanceApprovalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and(resolve(AppearanceSettings::class)->border_radius)->toBe('1rem')
        ->and(resolve(AppearanceSettings::class)->spacing_density)->toBe('comfortable')
        ->and(resolve(AppearanceSettings::class)->font_family)->toBe('instrument-sans')
        ->and(resolve(AppearanceSettings::class)->sidebar_variant)->toBe('floating');

    $this->actingAs($requester)
        ->patchJson(route('tyanc.settings.user-defaults.update'), [
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'appearance' => 'dark',
            'request_note' => 'Please approve this user default update.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.action', PermissionKey::tyanc('settings', 'update'));

    $userDefaultsApprovalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $userDefaultsApprovalRequest), [
            'review_note' => 'Approved defaults update.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.settings.user-defaults.update'), [
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'appearance' => 'dark',
        ])
        ->assertOk()
        ->assertJsonPath('settings.locale', 'id')
        ->assertJsonPath('settings.timezone', 'Asia/Makassar')
        ->assertJsonPath('settings.appearance', 'dark');

    expect($userDefaultsApprovalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and(resolve(UserDefaultsSettings::class)->locale)->toBe('id')
        ->and(resolve(UserDefaultsSettings::class)->timezone)->toBe('Asia/Makassar')
        ->and(resolve(UserDefaultsSettings::class)->appearance)->toBe('dark');
});

it('defers user deletion through the governed action gateway and consumes the grant on retry', function (): void {
    $managerRole = governedRole('Delete User Managers', 50);
    $reviewerRole = governedRole('Delete User Reviewers', 80);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ], $managerRole);

    $reviewer = governedUser([
        PermissionKey::tyanc('users', 'delete'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Delete Me',
    ]);

    governedRule(PermissionKey::tyanc('users', 'delete'), $reviewerRole);

    $this->actingAs($requester)
        ->deleteJson(route('tyanc.users.destroy', $managedUser), [
            'request_note' => 'Please approve deleting this user.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('executed', false)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending)
        ->assertJsonPath('approval.subject_name', 'Delete Me');

    expect($managedUser->fresh())->not->toBeNull();

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

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

it('rejects avatar changes while user update approval is required', function (): void {
    $managerRole = governedRole('Avatar User Managers', 50);
    $reviewerRole = governedRole('Avatar User Reviewers', 80);
    $targetRole = governedRole('Avatar Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    governedUser([
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

    governedRule(PermissionKey::tyanc('users', 'update'), $reviewerRole);

    $this->actingAs($requester)
        ->post(route('tyanc.users.update', $managedUser), [
            '_method' => 'PATCH',
            'name' => 'Avatar Target',
            'username' => 'avatar-target',
            'email' => 'avatar-target@example.com',
            'status' => UserStatus::Active->value,
            'locale' => 'en',
            'timezone' => 'UTC',
            'roles' => [$targetRole->name],
            'permissions' => [],
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
            'request_note' => 'Please approve this avatar update.',
        ])
        ->assertSessionHasErrors('avatar');

    $managedUser->forceFill([
        'avatar' => 'avatars/current.png',
    ])->save();

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            'name' => 'Avatar Target',
            'username' => 'avatar-target',
            'email' => 'avatar-target@example.com',
            'status' => UserStatus::Active->value,
            'locale' => 'en',
            'timezone' => 'UTC',
            'roles' => [$targetRole->name],
            'permissions' => [],
            'remove_avatar' => true,
            'request_note' => 'Please approve removing this avatar.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('remove_avatar');

    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and($managedUser->fresh()->avatar)->toBe('avatars/current.png');
});

it('redirects back to the user edit page when an approval submission comes from the edit form', function (): void {
    $managerRole = governedRole('Redirect User Managers', 50);
    $reviewerRole = governedRole('Redirect User Reviewers', 80);
    $targetRole = governedRole('Redirect Managed User', 10);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    governedUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Redirect Target',
        'username' => 'redirect-target',
        'email' => 'redirect-target@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    governedRule(PermissionKey::tyanc('users', 'update'), $reviewerRole);

    $this->actingAs($requester)
        ->from(route('tyanc.users.edit', $managedUser))
        ->post(route('tyanc.users.update', $managedUser), [
            '_method' => 'PATCH',
            'name' => 'Redirect Target',
            'username' => 'redirect-target',
            'email' => 'redirect-target@example.com',
            'status' => UserStatus::Active->value,
            'locale' => 'en',
            'timezone' => 'UTC',
            'roles' => [$targetRole->name],
            'permissions' => [],
            'request_note' => 'Please approve this update.',
        ])
        ->assertRedirect(route('tyanc.users.edit', $managedUser));
});

it('redirects back to the user show page when a delete approval submission comes from the detail page', function (): void {
    $managerRole = governedRole('Delete Redirect Managers', 50);
    $reviewerRole = governedRole('Delete Redirect Reviewers', 80);

    $requester = governedUser([
        PermissionKey::tyanc('users', 'manage'),
    ], $managerRole);

    governedUser([
        PermissionKey::tyanc('users', 'delete'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Delete Redirect Target',
    ]);

    governedRule(PermissionKey::tyanc('users', 'delete'), $reviewerRole);

    $this->actingAs($requester)
        ->from(route('tyanc.users.show', $managedUser))
        ->delete(route('tyanc.users.destroy', $managedUser), [
            'request_note' => 'Please approve this deletion.',
        ])
        ->assertRedirect(route('tyanc.users.show', $managedUser));
});
