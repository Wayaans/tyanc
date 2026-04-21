<?php

declare(strict_types=1);

use App\Enums\ApprovalMode;
use App\Enums\UserStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;

function userDraftPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function userDraftRole(string $name, int $level): Role
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

function userDraftUser(array $permissions, ?Role $role = null): User
{
    $user = User::factory()->create();

    if ($role instanceof Role) {
        $user->assignRole($role);
    }

    $user->givePermissionTo(array_map(userDraftPermission(...), $permissions));

    return $user;
}

function setUserDraftApprovalConfig(): void
{
    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'update' => [
                            'mode' => ApprovalMode::Draft->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                ['role' => 'Draft Reviewers', 'label' => 'User update review'],
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

function userDraftRule(Role $reviewerRole): ApprovalRule
{
    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->draftMode()
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
function userDraftPayload(Role $role, array $overrides = []): array
{
    return [
        ...[
            'name' => 'Drafted User',
            'username' => 'drafted-user',
            'email' => 'drafted-user@example.com',
            'status' => UserStatus::Suspended->value,
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'roles' => [$role->name],
            'permissions' => [],
        ],
        ...$overrides,
    ];
}

it('flashes shared toasts for web-based draft save, submit, and commit flows', function (): void {
    $this->seed(AppRegistrySeeder::class);
    setUserDraftApprovalConfig();

    $managerRole = userDraftRole('Draft Managers', 50);
    $reviewerRole = userDraftRole('Draft Reviewers', 80);
    $targetRole = userDraftRole('Draft Managed User', 10);

    $requester = userDraftUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('my_requests', 'viewany'),
    ], $managerRole);

    $reviewer = userDraftUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Original User',
        'username' => 'original-user',
        'email' => 'original-user@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    userDraftRule($reviewerRole);

    $this->actingAs($requester)
        ->from(route('tyanc.users.edit', $managedUser))
        ->patch(route('tyanc.users.update', $managedUser), userDraftPayload($targetRole))
        ->assertRedirect(route('tyanc.users.edit', $managedUser))
        ->assertSessionHas('toast', fn (array $toast): bool => ($toast['variant'] ?? null) === 'success'
            && ($toast['message'] ?? null) === 'Draft saved. Submit it for approval when you are ready.');

    $this->actingAs($requester)
        ->get(route('tyanc.users.edit', $managedUser))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Edit')
            ->where('flash.toast.variant', 'success')
            ->where('flash.toast.message', 'Draft saved. Submit it for approval when you are ready.'));

    $this->actingAs($requester)
        ->from(route('tyanc.users.edit', $managedUser))
        ->patch(route('tyanc.users.drafts.submit', $managedUser), [
            'request_note' => 'Please approve this saved draft.',
        ])
        ->assertRedirect(route('tyanc.users.edit', $managedUser))
        ->assertSessionHas('toast', fn (array $toast): bool => ($toast['variant'] ?? null) === 'success'
            && ($toast['message'] ?? null) === 'Draft submitted for approval.');

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved draft.',
        ])
        ->assertOk();

    $this->actingAs($requester)
        ->patch(route('tyanc.users.drafts.commit', $managedUser))
        ->assertRedirect(route('tyanc.users.show', $managedUser))
        ->assertSessionHas('toast', fn (array $toast): bool => ($toast['variant'] ?? null) === 'success'
            && ($toast['message'] ?? null) === 'Approved draft committed.');

    $this->actingAs($requester)
        ->get(route('tyanc.users.show', $managedUser))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Show')
            ->where('flash.toast.variant', 'success')
            ->where('flash.toast.message', 'Approved draft committed.'));
});

it('saves, submits, approves, and commits a user update draft', function (): void {
    $this->seed(AppRegistrySeeder::class);
    setUserDraftApprovalConfig();

    $managerRole = userDraftRole('Draft Managers', 50);
    $reviewerRole = userDraftRole('Draft Reviewers', 80);
    $targetRole = userDraftRole('Draft Managed User', 10);

    $requester = userDraftUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('my_requests', 'viewany'),
    ], $managerRole);

    $reviewer = userDraftUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Original User',
        'username' => 'original-user',
        'email' => 'original-user@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    userDraftRule($reviewerRole);

    $payload = userDraftPayload($targetRole, [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), $payload)
        ->assertOk()
        ->assertJsonPath('executed', false)
        ->assertJsonPath('mode', ApprovalMode::Draft->value)
        ->assertJsonPath('draft.state', 'draft')
        ->assertJsonPath('draft.has_password_change', true)
        ->assertJsonPath('draft.form_values.email', 'drafted-user@example.com');

    $draft = UserUpdateDraft::query()->where('user_id', $managedUser->id)->firstOrFail();

    expect($managedUser->fresh()->email)->toBe('original-user@example.com')
        ->and($draft->revision)->toBe(1)
        ->and($draft->hasPasswordChange())->toBeTrue();

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.drafts.submit', $managedUser), [
            'request_note' => 'Please approve this saved draft.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.mode', ApprovalMode::Draft->value)
        ->assertJsonPath('approval.subject_revision', '1')
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved draft.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.drafts.commit', $managedUser))
        ->assertOk()
        ->assertJsonPath('user.email', 'drafted-user@example.com')
        ->assertJsonPath('user.status', UserStatus::Suspended->value);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($draft->fresh()->committed_at)->not->toBeNull()
        ->and($managedUser->fresh()->email)->toBe('drafted-user@example.com')
        ->and($managedUser->fresh()->status)->toBe(UserStatus::Suspended)
        ->and($managedUser->fresh()->locale)->toBe('id')
        ->and($managedUser->fresh()->timezone)->toBe('Asia/Makassar');
});

it('invalidates older approved revisions and blocks committing a stale user draft', function (): void {
    $this->seed(AppRegistrySeeder::class);
    setUserDraftApprovalConfig();

    $managerRole = userDraftRole('Draft Managers', 50);
    $reviewerRole = userDraftRole('Draft Reviewers', 80);
    $targetRole = userDraftRole('Draft Managed User', 10);

    $requester = userDraftUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('my_requests', 'viewany'),
    ], $managerRole);

    $reviewer = userDraftUser([
        PermissionKey::tyanc('users', 'update'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ], $reviewerRole);

    $managedUser = User::factory()->create([
        'name' => 'Original User',
        'username' => 'original-user',
        'email' => 'original-user@example.com',
        'status' => UserStatus::Active,
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);
    $managedUser->assignRole($targetRole);

    userDraftRule($reviewerRole);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), userDraftPayload($targetRole))
        ->assertOk()
        ->assertJsonPath('draft.revision', 1);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.drafts.submit', $managedUser), [
            'request_note' => 'Please approve revision one.',
        ])
        ->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved revision one.',
        ])
        ->assertOk();

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.update', $managedUser), userDraftPayload($targetRole, [
            'email' => 'drafted-user+v2@example.com',
        ]))
        ->assertOk()
        ->assertJsonPath('draft.revision', 2);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusExpired);

    $this->actingAs($requester)
        ->patchJson(route('tyanc.users.drafts.commit', $managedUser))
        ->assertStatus(422)
        ->assertJsonValidationErrors('approval');

    expect($managedUser->fresh()->email)->toBe('original-user@example.com');
});
