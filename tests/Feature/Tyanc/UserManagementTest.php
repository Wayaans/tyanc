<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\LocalDevelopmentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function userManager(): User
{
    $user = User::factory()->create();

    $permission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('users', 'manage'),
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    return $user;
}

it('renders the tyanc users index page for authorized managers', function (): void {
    $manager = userManager();

    $this->actingAs($manager)
        ->get(route('tyanc.users.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Index')
            ->where('usersTable.meta.total', 1));
});

it('renders the create, show, and edit user pages for authorized managers', function (): void {
    $manager = userManager();
    $managedUser = User::factory()->create([
        'username' => 'managed-user',
        'email' => 'managed@example.com',
        'status' => UserStatus::Active,
    ]);

    UserProfile::factory()->for($managedUser, 'user')->create([
        'first_name' => 'Managed',
        'last_name' => 'User',
    ]);

    $this->actingAs($manager)
        ->get(route('tyanc.users.create'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Create')
            ->where('user.status', UserStatus::Active->value)
            ->has('roles')
            ->has('permissions'));

    $this->actingAs($manager)
        ->get(route('tyanc.users.show', $managedUser))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Show')
            ->where('user.email', 'managed@example.com')
            ->where('abilities.update', true));

    $this->actingAs($manager)
        ->get(route('tyanc.users.edit', $managedUser))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Edit')
            ->where('user.username', 'managed-user')
            ->has('statuses'));
});

it('applies user search, sort, and role filters server-side', function (): void {
    $manager = userManager();

    $role = Role::query()->create([
        'name' => 'Editor',
        'guard_name' => 'web',
        'level' => 10,
    ]);

    $matchingUser = User::factory()->create([
        'username' => 'tyanc-editor',
        'email' => 'editor@example.com',
        'status' => UserStatus::Suspended,
        'locale' => 'id',
    ]);
    UserProfile::factory()->for($matchingUser, 'user')->create([
        'first_name' => 'Tyanc',
        'last_name' => 'Editor',
        'city' => 'Denpasar',
    ]);
    $matchingUser->assignRole($role);

    $otherUser = User::factory()->create([
        'username' => 'other-user',
        'email' => 'other@example.com',
        'status' => UserStatus::Active,
    ]);
    UserProfile::factory()->for($otherUser, 'user')->create([
        'first_name' => 'Other',
        'last_name' => 'Person',
    ]);

    $this->actingAs($manager)
        ->getJson(route('tyanc.users.index', [
            'filter' => [
                'search' => 'Tyanc',
                'status' => UserStatus::Suspended->value,
                'role' => 'Editor',
            ],
            'sort' => ['name'],
            'per_page' => 5,
        ]))
        ->assertOk()
        ->assertJsonPath('usersTable.meta.total', 1)
        ->assertJsonPath('usersTable.query.filter.search', 'Tyanc')
        ->assertJsonPath('usersTable.query.sort.0', 'name')
        ->assertJsonPath('usersTable.rows.0.username', 'tyanc-editor')
        ->assertJsonPath('usersTable.rows.0.roles.0', 'Editor');
});

it('lets user managers assign direct permissions without already holding each target permission', function (): void {
    $manager = userManager();
    $targetPermission = Permission::query()->firstOrCreate([
        'name' => 'demo.orders.approve',
        'guard_name' => 'web',
    ]);

    $this->actingAs($manager)
        ->postJson(route('tyanc.users.store'), [
            'username' => 'direct-permission-user',
            'email' => 'direct-permission@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'status' => UserStatus::Active->value,
            'locale' => 'en',
            'timezone' => 'UTC',
            'roles' => [],
            'permissions' => [$targetPermission->name],
        ])
        ->assertCreated()
        ->assertJsonPath('user.permissions.0', 'demo.orders.approve');

    expect(User::query()->where('email', 'direct-permission@example.com')->firstOrFail()->hasDirectPermission('demo.orders.approve'))->toBeTrue();
});

it('lets the baseline admin assign lower-level roles during user management', function (): void {
    $this->seed(LocalDevelopmentSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole((string) config('tyanc.reserved_roles.admin'));
    $admin->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('users', 'manage'),
        'guard_name' => 'web',
    ]));

    $assignableRole = Role::query()->create([
        'name' => 'Support',
        'guard_name' => 'web',
        'level' => 10,
    ]);

    $targetPermission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('settings', 'manage'),
        'guard_name' => 'web',
    ]);

    $this->actingAs($admin)
        ->postJson(route('tyanc.users.store'), [
            'username' => 'managed-by-manuse',
            'email' => 'manuse-managed@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'status' => UserStatus::Active->value,
            'locale' => 'en',
            'timezone' => 'UTC',
            'roles' => [$assignableRole->name],
            'permissions' => [$targetPermission->name],
        ])
        ->assertCreated()
        ->assertJsonPath('user.roles.0', 'Support')
        ->assertJsonPath('user.permissions.0', PermissionKey::tyanc('settings', 'manage'));

    $managedUser = User::query()->where('email', 'manuse-managed@example.com')->firstOrFail();

    expect($managedUser->hasRole('Support'))->toBeTrue()
        ->and($managedUser->hasDirectPermission(PermissionKey::tyanc('settings', 'manage')))->toBeTrue();
});

it('creates managed users with profile, avatar, roles, and permissions', function (): void {
    Storage::fake('public');

    $manager = userManager();

    $role = Role::query()->create([
        'name' => 'Support',
        'guard_name' => 'web',
        'level' => 5,
    ]);

    $directPermission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('settings', 'manage'),
        'guard_name' => 'web',
    ]);

    $manager->givePermissionTo($directPermission);

    $this->actingAs($manager)
        ->postJson(route('tyanc.users.store'), [
            'username' => 'managed-user',
            'email' => 'managed@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
            'status' => UserStatus::Active->value,
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'roles' => [$role->name],
            'permissions' => [$directPermission->name],
            'first_name' => 'Managed',
            'last_name' => 'User',
            'city' => 'Makassar',
            'social_links' => [
                'github' => 'https://github.com/managed-user',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('user.username', 'managed-user')
        ->assertJsonPath('user.locale', 'id')
        ->assertJsonPath('user.roles.0', 'Support')
        ->assertJsonPath('user.permissions.0', PermissionKey::tyanc('settings', 'manage'))
        ->assertJsonPath('user.city', 'Makassar');

    $managedUser = User::query()->where('email', 'managed@example.com')->first();

    expect($managedUser)->not->toBeNull()
        ->and($managedUser?->avatar)->not->toBeNull()
        ->and($managedUser?->hasRole('Support'))->toBeTrue()
        ->and($managedUser?->hasDirectPermission(PermissionKey::tyanc('settings', 'manage')))->toBeTrue()
        ->and($managedUser?->profile?->city)->toBe('Makassar')
        ->and($managedUser?->profile?->social_links)->toBe([
            'github' => 'https://github.com/managed-user',
        ]);
});

it('updates managed users and syncs their access', function (): void {
    $manager = userManager();

    $oldRole = Role::query()->create([
        'name' => 'Viewer',
        'guard_name' => 'web',
        'level' => 1,
    ]);
    $newRole = Role::query()->create([
        'name' => 'Operator',
        'guard_name' => 'web',
        'level' => 2,
    ]);

    $oldPermission = Permission::query()->firstOrCreate([
        'name' => 'demo.reports.view',
        'guard_name' => 'web',
    ]);
    $newPermission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('settings', 'manage'),
        'guard_name' => 'web',
    ]);

    $manager->givePermissionTo($newPermission);

    $managedUser = User::factory()->create([
        'username' => 'before-update',
        'email' => 'before@example.com',
        'status' => UserStatus::Active,
    ]);
    UserProfile::factory()->for($managedUser, 'user')->create([
        'city' => 'Denpasar',
        'company_name' => 'Before Co',
    ]);
    $managedUser->assignRole($oldRole);
    $managedUser->givePermissionTo($oldPermission);

    $this->actingAs($manager)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            'username' => 'after-update',
            'email' => 'after@example.com',
            'status' => UserStatus::Banned->value,
            'locale' => 'en',
            'timezone' => 'Asia/Jakarta',
            'roles' => [$newRole->name],
            'permissions' => [$newPermission->name],
            'first_name' => 'After',
            'last_name' => 'Update',
            'city' => 'Jakarta',
            'company_name' => 'After Co',
            'password' => 'updated-password123',
            'password_confirmation' => 'updated-password123',
        ])
        ->assertOk()
        ->assertJsonPath('user.username', 'after-update')
        ->assertJsonPath('user.email', 'after@example.com')
        ->assertJsonPath('user.status', UserStatus::Banned->value)
        ->assertJsonPath('user.roles.0', 'Operator')
        ->assertJsonPath('user.permissions.0', PermissionKey::tyanc('settings', 'manage'))
        ->assertJsonPath('user.city', 'Jakarta');

    $managedUser->refresh()->load('profile', 'roles', 'permissions');

    expect($managedUser->username)->toBe('after-update')
        ->and($managedUser->email)->toBe('after@example.com')
        ->and($managedUser->status)->toBe(UserStatus::Banned)
        ->and($managedUser->timezone)->toBe('Asia/Jakarta')
        ->and($managedUser->hasRole('Operator'))->toBeTrue()
        ->and($managedUser->hasRole('Viewer'))->toBeFalse()
        ->and($managedUser->hasDirectPermission(PermissionKey::tyanc('settings', 'manage')))->toBeTrue()
        ->and($managedUser->hasDirectPermission('demo.reports.view'))->toBeFalse()
        ->and($managedUser->profile?->city)->toBe('Jakarta')
        ->and($managedUser->profile?->company_name)->toBe('After Co');
});

it('supports method-spoofed multipart user updates for the Inertia edit form flow', function (): void {
    Storage::fake('public');

    $manager = userManager();

    $newRole = Role::query()->create([
        'name' => 'Operator',
        'guard_name' => 'web',
        'level' => 2,
    ]);

    $newPermission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('settings', 'manage'),
        'guard_name' => 'web',
    ]);

    $managedUser = User::factory()->create([
        'username' => 'method-spoof-target',
        'email' => 'method-spoof-before@example.com',
        'status' => UserStatus::Active,
    ]);

    $this->actingAs($manager)
        ->post(route('tyanc.users.update', $managedUser), [
            '_method' => 'PATCH',
            'username' => 'method-spoof-updated',
            'email' => 'method-spoof-after@example.com',
            'status' => UserStatus::Active->value,
            'locale' => 'en',
            'timezone' => 'UTC',
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
            'roles' => [$newRole->name],
            'permissions' => [$newPermission->name],
        ])
        ->assertRedirect(route('tyanc.users.show', $managedUser));

    $managedUser->refresh()->load('roles', 'permissions');

    expect($managedUser->avatar)->not->toBeNull()
        ->and($managedUser->username)->toBe('method-spoof-updated')
        ->and($managedUser->email)->toBe('method-spoof-after@example.com')
        ->and($managedUser->hasRole('Operator'))->toBeTrue()
        ->and($managedUser->hasDirectPermission(PermissionKey::tyanc('settings', 'manage')))->toBeTrue();
});

it('suspends managed users', function (): void {
    $manager = userManager();
    $managedUser = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    $this->actingAs($manager)
        ->patchJson(route('tyanc.users.suspend', $managedUser))
        ->assertOk()
        ->assertJsonPath('user.status', UserStatus::Suspended->value);

    expect($managedUser->fresh()->status)->toBe(UserStatus::Suspended);
});

it('soft deletes managed users', function (): void {
    $manager = userManager();
    $managedUser = User::factory()->create();

    $this->actingAs($manager)
        ->deleteJson(route('tyanc.users.destroy', $managedUser))
        ->assertNoContent();

    $this->assertSoftDeleted($managedUser);
});

it('forbids user management without the tyanc.users.manage permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tyanc.users.index'))
        ->assertForbidden();
});
