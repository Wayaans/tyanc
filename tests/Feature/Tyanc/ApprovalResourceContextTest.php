<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;

function contextPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function contextUser(array $permissions, ?Role $role = null): User
{
    $user = User::factory()->create();

    if ($role instanceof Role) {
        $user->assignRole($role);
    }

    $user->givePermissionTo(array_map(contextPermission(...), $permissions));

    return $user;
}

function createSubjectApprovalRequest(
    string $permissionName,
    User $requester,
    User $assignee,
    User|App $subject,
    string $actionLabel,
    string $stepLabel,
): ApprovalRequest {
    $reviewRole = Role::query()->create([
        'name' => sprintf('Reviewers %s', str_replace('.', '-', $permissionName)),
        'guard_name' => 'web',
        'level' => 60,
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission($permissionName)
        ->enabled()
        ->create();

    $step = $approvalRule->steps()->create([
        'role_id' => $reviewRole->id,
        'step_order' => 1,
        'label' => $stepLabel,
    ]);

    $parsed = PermissionKey::parse($permissionName);

    $approvalRequest = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->create([
            'action' => $permissionName,
            'app_key' => $parsed['app'],
            'resource_key' => $parsed['resource'],
            'action_key' => $parsed['action'],
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'requested_by_id' => $requester->id,
            'payload' => [
                'action_label' => $actionLabel,
                'subject_label' => method_exists($subject, 'approvalSubjectLabel')
                    ? $subject->approvalSubjectLabel()
                    : null,
            ],
        ]);

    $approvalRequest->assignments()->create([
        'approval_rule_step_id' => $step->id,
        'step_order_snapshot' => 1,
        'step_label_snapshot' => $stepLabel,
        'assigned_to_id' => $assignee->id,
        'status' => ApprovalAssignment::StatusPending,
    ]);

    return $approvalRequest;
}

it('shows a pending approval banner on governed user pages without exposing cumpu links to non-reviewers', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $manager = contextUser([
        PermissionKey::tyanc('users', 'manage'),
    ]);

    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $subject = User::factory()->create([
        'name' => 'Ayu Managed',
    ]);

    $approvalRequest = createSubjectApprovalRequest(
        permissionName: PermissionKey::tyanc('users', 'update'),
        requester: $requester,
        assignee: $assignee,
        subject: $subject,
        actionLabel: 'Update user profile',
        stepLabel: 'Profile review',
    );

    $this->actingAs($manager)
        ->get(route('tyanc.users.show', $subject))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Show')
            ->where('approvalContext.pending_count', 1)
            ->where('approvalContext.can_view_requests', false)
            ->where('approvalContext.latest_pending_request.action_label', 'Approval request')
            ->where('approvalContext.latest_pending_request.current_step_label', null)
            ->where('approvalContext.latest_pending_request.requested_by_name', null)
            ->where('approvalContext.latest_pending_request.detail_url', null)
            ->where('approvalContext.history', []));

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusPending);
});

it('shows exact-record approval history links on user pages for users who can access cumpu approvals', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $manager = contextUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'viewany'),
    ]);

    $requester = User::factory()->create([
        'name' => 'Request Owner',
    ]);
    $assignee = User::factory()->create();
    $subject = User::factory()->create([
        'name' => 'Komang Example',
    ]);

    $approvalRequest = createSubjectApprovalRequest(
        permissionName: PermissionKey::tyanc('users', 'update'),
        requester: $requester,
        assignee: $assignee,
        subject: $subject,
        actionLabel: 'Update user profile',
        stepLabel: 'Profile review',
    );

    $this->actingAs($manager)
        ->get(route('tyanc.users.edit', $subject))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Edit')
            ->where('approvalContext.pending_count', 1)
            ->where('approvalContext.can_view_requests', true)
            ->where('approvalContext.history.0.id', (string) $approvalRequest->id)
            ->where('approvalContext.history.0.requested_by_name', 'Request Owner')
            ->where('approvalContext.history.0.detail_url', route('cumpu.approvals.show', $approvalRequest, absolute: false)));
});

it('shares approval context on tyanc governance pages beyond users', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $manager = contextUser([
        PermissionKey::tyanc('roles', 'manage'),
        PermissionKey::tyanc('apps', 'manage'),
        PermissionKey::tyanc('activity_log', 'viewany'),
        PermissionKey::tyanc('settings', 'manage'),
        PermissionKey::cumpu('approvals', 'viewany'),
    ]);

    $requester = User::factory()->create([
        'name' => 'Flow Owner',
    ]);
    $assignee = User::factory()->create();
    $managedApp = App::factory()->create([
        'key' => 'governed-app',
        'label' => 'Governed App',
        'route_prefix' => 'governed-app',
        'permission_namespace' => 'governed_app',
    ]);

    ApprovalRequest::factory()->create([
        'rule_id' => null,
        'action' => PermissionKey::tyanc('roles', 'update'),
        'app_key' => 'tyanc',
        'resource_key' => 'roles',
        'action_key' => 'update',
        'subject_type' => null,
        'subject_id' => null,
        'requested_by_id' => $requester->id,
        'payload' => [
            'action_label' => 'Update role access',
        ],
    ]);

    $appApproval = createSubjectApprovalRequest(
        permissionName: PermissionKey::tyanc('apps', 'toggle'),
        requester: $requester,
        assignee: $assignee,
        subject: $managedApp,
        actionLabel: 'Toggle governed app',
        stepLabel: 'App governance review',
    );

    ApprovalRequest::factory()->create([
        'rule_id' => null,
        'action' => PermissionKey::tyanc('activity_log', 'export'),
        'app_key' => 'tyanc',
        'resource_key' => 'activity_log',
        'action_key' => 'export',
        'subject_type' => null,
        'subject_id' => null,
        'requested_by_id' => $requester->id,
        'payload' => [
            'action_label' => 'Export activity log',
        ],
    ]);

    ApprovalRequest::factory()->create([
        'rule_id' => null,
        'action' => PermissionKey::tyanc('settings', 'update'),
        'app_key' => 'tyanc',
        'resource_key' => 'settings',
        'action_key' => 'update',
        'subject_type' => null,
        'subject_id' => null,
        'requested_by_id' => $requester->id,
        'payload' => [
            'action_label' => 'Update platform settings',
        ],
    ]);

    $this->actingAs($manager)
        ->get(route('tyanc.roles.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/roles/Index')
            ->where('approvalContext.pending_count', 1)
            ->where('approvalContext.latest_pending_request.action_label', 'Update role access'));

    $this->actingAs($manager)
        ->get(route('tyanc.apps.edit', $managedApp))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/apps/Edit')
            ->where('approvalContext.pending_count', 1)
            ->where('approvalContext.history.0.detail_url', route('cumpu.approvals.show', $appApproval, absolute: false)));

    $this->actingAs($manager)
        ->get(route('tyanc.activity-log.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/activity-log/Index')
            ->where('approvalContext.pending_count', 1)
            ->where('approvalContext.latest_pending_request.action_label', 'Export activity log'));

    $this->actingAs($manager)
        ->get(route('tyanc.settings.application.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/settings/Application')
            ->where('approvalContext.pending_count', 1)
            ->where('approvalContext.latest_pending_request.action_label', 'Update platform settings'));
});

it('shares governed action state for user update and delete actions', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewRole = Role::query()->create([
        'name' => 'Governed User Reviewers',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    $manager = contextUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $subject = User::factory()->create([
        'name' => 'Governed Context User',
    ]);

    $updateRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->enabled()
        ->create();
    $updateRule->steps()->create([
        'role_id' => $reviewRole->id,
        'step_order' => 1,
        'label' => 'User update review',
    ]);

    $deleteRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'delete'))
        ->enabled()
        ->create();
    $deleteRule->steps()->create([
        'role_id' => $reviewRole->id,
        'step_order' => 1,
        'label' => 'User delete review',
    ]);

    $pendingUpdate = ApprovalRequest::factory()
        ->for($updateRule, 'rule')
        ->create([
            'action' => PermissionKey::tyanc('users', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'update',
            'status' => ApprovalRequest::StatusPending,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->id,
            'requested_by_id' => $manager->id,
            'payload' => [
                'action_label' => 'Update user profile',
                'subject_label' => $subject->approvalSubjectLabel(),
            ],
        ]);

    $approvedDelete = ApprovalRequest::factory()
        ->for($deleteRule, 'rule')
        ->create([
            'action' => PermissionKey::tyanc('users', 'delete'),
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'delete',
            'status' => ApprovalRequest::StatusApproved,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->id,
            'requested_by_id' => $manager->id,
            'reviewed_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
            'payload' => [
                'action_label' => 'Delete user',
                'subject_label' => $subject->approvalSubjectLabel(),
            ],
        ]);

    $this->actingAs($manager)
        ->get(route('tyanc.users.edit', $subject))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Edit')
            ->where('approvalContext.governed_actions.update.approval_enabled', true)
            ->where('approvalContext.governed_actions.update.approval_required', false)
            ->where('approvalContext.governed_actions.update.has_blocking_request', true)
            ->where('approvalContext.governed_actions.update.relevant_request.id', (string) $pendingUpdate->id)
            ->where('approvalContext.governed_actions.update.relevant_request.detail_url', route('cumpu.approvals.show', $pendingUpdate, absolute: false))
            ->where('approvalContext.governed_actions.delete.approval_enabled', true)
            ->where('approvalContext.governed_actions.delete.approval_required', false)
            ->where('approvalContext.governed_actions.delete.has_usable_grant', true)
            ->where('approvalContext.governed_actions.delete.relevant_request.id', (string) $approvedDelete->id)
            ->where('approvalContext.governed_actions.delete.relevant_request.status', ApprovalRequest::StatusApproved)
            ->where('approvalContext.governed_actions.delete.relevant_request.is_grant_usable', true)
            ->where('approvalContext.governed_actions.delete.relevant_request.consumed_at', null)
            ->where('approvalContext.governed_actions.delete.relevant_request.expires_at', $approvedDelete->expires_at?->toIso8601String()));

    $this->actingAs($manager)
        ->get(route('tyanc.users.show', $subject))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Show')
            ->where('approvalContext.governed_actions.update.relevant_request.id', (string) $pendingUpdate->id)
            ->where('approvalContext.governed_actions.delete.relevant_request.id', (string) $approvedDelete->id));
});

it('shares governed action detail links for requesters with my requests page access', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewRole = Role::query()->create([
        'name' => 'My Requests Context Reviewers',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    $manager = contextUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::cumpu('my_requests', 'viewany'),
    ]);

    $subject = User::factory()->create([
        'username' => 'context-request-subject',
        'email' => 'context-request-subject@example.com',
    ]);

    $updateRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->enabled()
        ->create();
    $updateRule->steps()->create([
        'role_id' => $reviewRole->id,
        'step_order' => 1,
        'label' => 'Context requester review',
    ]);

    $pendingUpdate = ApprovalRequest::factory()
        ->for($updateRule, 'rule')
        ->create([
            'action' => PermissionKey::tyanc('users', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'update',
            'status' => ApprovalRequest::StatusPending,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->id,
            'requested_by_id' => $manager->id,
            'payload' => [
                'action_label' => 'Update user',
                'subject_label' => $subject->approvalSubjectLabel(),
            ],
        ]);

    $this->actingAs($manager)
        ->get(route('tyanc.users.edit', $subject))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Edit')
            ->where('approvalContext.governed_actions.update.relevant_request.id', (string) $pendingUpdate->id)
            ->where('approvalContext.governed_actions.update.relevant_request.detail_url', route('cumpu.approvals.show', $pendingUpdate, absolute: false)));
});

it('shares role-level governed action state for role edits on the roles index page', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewRole = Role::query()->create([
        'name' => 'Role Update Reviewers',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    $manager = contextUser([
        PermissionKey::tyanc('roles', 'manage'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $subject = Role::query()->create([
        'name' => 'Governed Role',
        'guard_name' => 'web',
        'level' => 5,
    ]);

    $updateRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('roles', 'update'))
        ->enabled()
        ->create();
    $updateRule->steps()->create([
        'role_id' => $reviewRole->id,
        'step_order' => 1,
        'label' => 'Role update review',
    ]);

    $pendingUpdate = ApprovalRequest::factory()
        ->for($updateRule, 'rule')
        ->create([
            'action' => PermissionKey::tyanc('roles', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'roles',
            'action_key' => 'update',
            'status' => ApprovalRequest::StatusPending,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->id,
            'requested_by_id' => $manager->id,
            'payload' => [
                'action_label' => 'Update role',
                'subject_label' => $subject->approvalSubjectLabel(),
            ],
        ]);

    $this->actingAs($manager)
        ->get(route('tyanc.roles.index', [
            'filter' => ['search' => 'Governed Role'],
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/roles/Index')
            ->where('rolesTable.rows.0.id', $subject->id)
            ->where('rolesTable.rows.0.update_approval_state.approval_enabled', true)
            ->where('rolesTable.rows.0.update_approval_state.has_blocking_request', true)
            ->where('rolesTable.rows.0.update_approval_state.relevant_request.id', (string) $pendingUpdate->id)
            ->where('rolesTable.rows.0.update_approval_state.relevant_request.detail_url', route('cumpu.approvals.show', $pendingUpdate, absolute: false)));
});

it('shares governed action state for user import on the users index page', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewRole = Role::query()->create([
        'name' => 'Governed Import Reviewers',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    $manager = contextUser([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $importRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();
    $importRule->steps()->create([
        'role_id' => $reviewRole->id,
        'step_order' => 1,
        'label' => 'Import review',
    ]);

    $approvedImport = ApprovalRequest::factory()
        ->for($importRule, 'rule')
        ->create([
            'action' => PermissionKey::tyanc('users', 'import'),
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'status' => ApprovalRequest::StatusApproved,
            'subject_type' => null,
            'subject_id' => null,
            'requested_by_id' => $manager->id,
            'reviewed_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
            'payload' => [
                'action_label' => 'Users import',
                'subject_label' => 'users.xlsx',
            ],
        ]);

    $this->actingAs($manager)
        ->get(route('tyanc.users.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Index')
            ->where('approvalContext.governed_actions.import.approval_enabled', true)
            ->where('approvalContext.governed_actions.import.has_usable_grant', true)
            ->where('approvalContext.governed_actions.import.relevant_request.id', (string) $approvedImport->id)
            ->where('approvalContext.governed_actions.import.relevant_request.is_grant_usable', true)
            ->where('approvalContext.governed_actions.import.relevant_request.detail_url', route('cumpu.approvals.show', $approvedImport, absolute: false)));
});
