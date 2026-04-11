<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;
use Database\Seeders\PermissionsSyncSeeder;

function tyancApiManager(array $permissions): User
{
    test()->seed([AppRegistrySeeder::class, PermissionsSyncSeeder::class]);

    $user = User::factory()->create();
    $user->givePermissionTo(
        collect($permissions)
            ->map(fn (string $permissionName): Permission => Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]))
            ->all(),
    );

    return $user;
}

it('returns paginated API envelopes for governance endpoints', function (): void {
    $manager = tyancApiManager([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::tyanc('apps', 'manage'),
        PermissionKey::tyanc('roles', 'manage'),
        PermissionKey::tyanc('permissions', 'manage'),
        PermissionKey::tyanc('access_matrix', 'manage'),
        PermissionKey::tyanc('messages', 'viewany'),
    ]);

    $role = Role::query()->create([
        'name' => 'API Reviewer',
        'guard_name' => 'web',
        'level' => 20,
    ]);
    $role->givePermissionTo(Permission::query()->where('name', PermissionKey::tyanc('users', 'manage'))->firstOrFail());

    $participant = User::factory()->create();
    $conversation = Conversation::factory()->for($manager, 'creator')->create([
        'last_message_at' => now()->subMinute(),
    ]);
    $conversation->participants()->attach([
        (string) $manager->id => [
            'last_read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        (string) $participant->id => [
            'last_read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    Message::factory()->for($conversation)->for($participant, 'sender')->create([
        'body' => 'Please check the v1 endpoints.',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    App::factory()->create([
        'key' => 'tasks',
        'label' => 'Tasks',
        'route_prefix' => 'tasks',
        'permission_namespace' => 'tasks',
        'enabled' => true,
    ]);

    $this->actingAs($manager)
        ->getJson('http://api.tyanc.test/api/v1/users')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['total', 'page', 'per_page', 'last_page'],
            'context',
        ])
        ->assertJsonPath('meta.total', 2);

    $this->actingAs($manager)
        ->getJson('http://api.tyanc.test/api/v1/apps')
        ->assertOk()
        ->assertJsonPath('meta.total', 3);

    $this->actingAs($manager)
        ->getJson('http://api.tyanc.test/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'API Reviewer');

    $this->actingAs($manager)
        ->getJson('http://api.tyanc.test/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('context.summary.total', count(PermissionKey::all()));

    $this->actingAs($manager)
        ->getJson(sprintf('http://api.tyanc.test/api/v1/access-matrix?app=tyanc&role_id=%d', $role->id))
        ->assertOk()
        ->assertJsonPath('context.selected_role_id', $role->id)
        ->assertJsonPath('context.selected_app_key', 'tyanc');

    $this->actingAs($manager)
        ->getJson('http://api.tyanc.test/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $conversation->id)
        ->assertJsonPath('context.unread_count', 0);
});

it('returns a permission-aware error payload when access is forbidden', function (): void {
    $user = tyancApiManager([]);

    $this->actingAs($user)
        ->getJson('http://api.tyanc.test/api/v1/users')
        ->assertForbidden()
        ->assertJsonPath('status', 403)
        ->assertJsonPath('code', 'forbidden')
        ->assertJsonPath('permission', PermissionKey::tyanc('users', 'viewany'));
});
