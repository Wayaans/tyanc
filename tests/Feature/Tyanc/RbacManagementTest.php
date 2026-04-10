<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;
use Database\Seeders\PermissionsSyncSeeder;
use Spatie\Activitylog\Models\Activity;

function rbacPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function rbacManager(): User
{
    test()->seed([AppRegistrySeeder::class, PermissionsSyncSeeder::class]);

    $role = Role::query()->firstOrCreate([
        'name' => 'RBAC Manager',
        'guard_name' => 'web',
    ], [
        'level' => 50,
    ]);

    $permissions = [
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::tyanc('settings', 'manage'),
        PermissionKey::tyanc('roles', 'manage'),
        PermissionKey::tyanc('permissions', 'manage'),
        PermissionKey::tyanc('access_matrix', 'manage'),
        PermissionKey::tyanc('activity_log', 'view'),
        'demo.dashboard.viewany',
        'demo.orders.view',
        'demo.reports.view',
    ];

    $role->syncPermissions(array_map(rbacPermission(...), $permissions));

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('exposes config-driven permission options and a catalog summary', function (): void {
    $manager = rbacManager();

    $this->actingAs($manager)
        ->getJson(route('tyanc.permissions.index'))
        ->assertOk()
        ->assertJsonPath('permissionOptions.apps.0.value', 'tyanc')
        ->assertJsonPath('permissionOptions.resources.tyanc.0.value', 'dashboard')
        ->assertJsonPath('permissionsTable.summary.total', count(PermissionKey::all()));
});

it('creates roles as metadata first and assigns permissions through a dedicated route', function (): void {
    $manager = rbacManager();

    $this->actingAs($manager)
        ->postJson(route('tyanc.roles.store'), [
            'name' => 'Order Approver',
            'level' => 10,
        ])
        ->assertCreated()
        ->assertJsonPath('role.name', 'Order Approver')
        ->assertJsonPath('role.permission_count', 0);

    $role = Role::query()->where('name', 'Order Approver')->firstOrFail();

    Permission::query()
        ->whereIn('name', ['demo.orders.view', 'demo.reports.view'])
        ->delete();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.roles.permissions.update', $role), [
            'permissions' => ['demo.orders.view', 'demo.reports.view'],
        ])
        ->assertOk()
        ->assertJsonPath('role.permissions.0', 'demo.orders.view')
        ->assertJsonPath('role.permissions.1', 'demo.reports.view');

    expect($role->fresh()->hasPermissionTo('demo.orders.view'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('demo.reports.view'))->toBeTrue();
});

it('updates role metadata separately from permission assignment and records audit entries', function (): void {
    $manager = rbacManager();

    Activity::query()->delete();

    $this->actingAs($manager)
        ->postJson(route('tyanc.roles.store'), [
            'name' => 'Support Lead',
            'level' => 10,
        ])
        ->assertCreated()
        ->assertJsonPath('role.permissions', []);

    $role = Role::query()->where('name', 'Support Lead')->firstOrFail();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.roles.update', $role), [
            'name' => 'Operations Lead',
            'level' => 15,
        ])
        ->assertOk()
        ->assertJsonPath('role.name', 'Operations Lead')
        ->assertJsonPath('role.level', 15);

    $this->actingAs($manager)
        ->patchJson(route('tyanc.roles.permissions.update', $role), [
            'permissions' => [PermissionKey::tyanc('users', 'manage')],
        ])
        ->assertOk()
        ->assertJsonPath('role.permissions.0', PermissionKey::tyanc('users', 'manage'));

    $role->refresh();

    expect($role->hasPermissionTo(PermissionKey::tyanc('users', 'manage')))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'rbac')->where('description', 'Role created')->exists())->toBeTrue()
        ->and(Activity::query()->where('log_name', 'rbac')->where('description', 'Role updated')->exists())->toBeTrue()
        ->and(Activity::query()->where('log_name', 'rbac')->where('description', 'Role permissions assigned')->exists())->toBeTrue();
});

it('keeps the permissions page catalog-only and syncs from the source of truth', function (): void {
    $manager = rbacManager();

    Permission::query()->where('name', 'demo.reports.export')->delete();

    $this->actingAs($manager)
        ->postJson(route('tyanc.permissions.sync'))
        ->assertOk()
        ->assertJsonPath('sync.total', count(PermissionKey::all()));

    expect(Permission::query()->where('name', 'demo.reports.export')->exists())->toBeTrue();

    $this->actingAs($manager)
        ->post('/tyanc/permissions', [])
        ->assertMethodNotAllowed();

    $this->actingAs($manager)
        ->patch('/tyanc/permissions/1', [])
        ->assertNotFound();

    $this->actingAs($manager)
        ->delete('/tyanc/permissions/1')
        ->assertNotFound();
});

it('syncs the access matrix and records an audit entry', function (): void {
    $manager = rbacManager();

    $role = Role::query()->create([
        'name' => 'Approver',
        'guard_name' => 'web',
        'level' => 10,
    ]);

    Activity::query()->delete();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.access-matrix.update'), [
            'role' => $role->name,
            'permissions' => [
                'demo.orders.view',
                'demo.reports.view',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('accessMatrix.effective_preview.roles.0', 'Approver')
        ->assertJsonPath('accessMatrix.effective_preview.permissions.0', 'demo.orders.view');

    $role->refresh();

    expect($role->hasPermissionTo('demo.orders.view'))->toBeTrue()
        ->and($role->hasPermissionTo('demo.reports.view'))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'rbac')->where('description', 'Access matrix synced')->exists())->toBeTrue();
});

it('grants demo dashboard access through an access-matrix-synced role', function (): void {
    $manager = rbacManager();

    $viewerRole = Role::query()->create([
        'name' => 'Demo Viewer',
        'guard_name' => 'web',
        'level' => 10,
    ]);

    $this->actingAs($manager)
        ->patchJson(route('tyanc.access-matrix.update'), [
            'role' => $viewerRole->name,
            'permissions' => ['demo.dashboard.viewany'],
        ])
        ->assertOk()
        ->assertJsonPath('accessMatrix.effective_preview.permissions.0', 'demo.dashboard.viewany');

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $this->actingAs($viewer)
        ->get(route('demo.dashboard'))
        ->assertOk();
});

it('supports assigning multiple roles to a user and resolves effective permissions', function (): void {
    $manager = rbacManager();

    $editor = Role::query()->create([
        'name' => 'Editor',
        'guard_name' => 'web',
        'level' => 10,
    ]);
    $editor->givePermissionTo(rbacPermission('demo.orders.view'));

    $reviewer = Role::query()->create([
        'name' => 'Reviewer',
        'guard_name' => 'web',
        'level' => 5,
    ]);
    $reviewer->givePermissionTo(rbacPermission('demo.reports.view'));

    $managedUser = User::factory()->create();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            'username' => 'multi-role-user',
            'email' => 'multi-role@example.com',
            'status' => 'active',
            'locale' => 'en',
            'timezone' => 'UTC',
            'roles' => ['Editor', 'Reviewer'],
            'permissions' => [],
        ])
        ->assertOk()
        ->assertJsonPath('user.roles.0', 'Editor')
        ->assertJsonPath('user.roles.1', 'Reviewer');

    $managedUser->refresh();

    expect($managedUser->getRoleNames()->sort()->values()->all())->toBe(['Editor', 'Reviewer'])
        ->and($managedUser->hasPermissionTo('demo.orders.view'))->toBeTrue()
        ->and($managedUser->hasPermissionTo('demo.reports.view'))->toBeTrue();
});

it('denies role creation above the acting user hierarchy level', function (): void {
    $manager = rbacManager();

    $this->actingAs($manager)
        ->postJson(route('tyanc.roles.store'), [
            'name' => 'Too Powerful',
            'level' => 50,
        ])
        ->assertForbidden();
});

it('protects reserved roles and denies direct governance-route access without permission', function (): void {
    $manager = rbacManager();

    $reservedRole = Role::query()->updateOrCreate(
        [
            'name' => (string) config('tyanc.reserved_roles.admin'),
            'guard_name' => 'web',
        ],
        [
            'level' => 0,
        ],
    );

    $plainUser = User::factory()->create();

    $this->actingAs($plainUser)
        ->getJson(route('tyanc.roles.index'))
        ->assertForbidden();

    $this->actingAs($plainUser)
        ->getJson(route('tyanc.permissions.index'))
        ->assertForbidden();

    $this->actingAs($plainUser)
        ->getJson(route('tyanc.access-matrix.index'))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.roles.update', $reservedRole), [
            'name' => 'Renamed Manuse',
            'level' => 0,
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->deleteJson(route('tyanc.roles.destroy', $reservedRole))
        ->assertForbidden();
});
