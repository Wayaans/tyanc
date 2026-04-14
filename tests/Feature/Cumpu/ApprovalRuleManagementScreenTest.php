<?php

declare(strict_types=1);

use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;

function approvalRuleManagementPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function approvalRuleManagementUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(approvalRuleManagementPermission(...), $permissions));

    return $user;
}

it('renders the managed approval capability screen', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = approvalRuleManagementUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->get(route('cumpu.approval-rules.index'))
        ->assertInertia(fn ($page) => $page
            ->component('cumpu/approval-rules/Index')
            ->where('abilities.manage', true));
});

it('passes rules with app, resource and action fields required by the filter bar', function (): void {
    $this->seed(AppRegistrySeeder::class);

    /** @var Role $role */
    $role = Role::query()->firstOrCreate(
        ['name' => 'Reviewer', 'guard_name' => 'web'],
        ['level' => 10],
    );

    ApprovalRule::factory()
        ->managed('tyanc.users.import')
        ->withRoleStep($role)
        ->create([
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'permission_name' => 'tyanc.users.import',
        ]);

    $user = approvalRuleManagementUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
    ]);

    $this->actingAs($user)
        ->get(route('cumpu.approval-rules.index'))
        ->assertInertia(fn ($page) => $page
            ->component('cumpu/approval-rules/Index')
            ->has('rules')
            ->has('rules.0', fn ($rule) => $rule
                ->has('app_key')
                ->has('app_label')
                ->has('resource_key')
                ->has('resource_label')
                ->has('action_key')
                ->has('action_label')
                ->etc()
            )
        );
});

it('toggles an approval rule via the toggle route used by the edit dialog', function (): void {
    $this->seed(AppRegistrySeeder::class);

    /** @var Role $role */
    $role = Role::query()->firstOrCreate(
        ['name' => 'Approver', 'guard_name' => 'web'],
        ['level' => 10],
    );

    $rule = ApprovalRule::factory()
        ->managed('tyanc.users.import')
        ->withRoleStep($role)
        ->create([
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'permission_name' => 'tyanc.users.import',
            'enabled' => false,
        ]);

    $user = approvalRuleManagementUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->patch(route('cumpu.approval-rules.toggle', $rule), ['enabled' => true])
        ->assertRedirect(route('cumpu.approval-rules.index'));

    expect($rule->fresh()?->enabled)->toBeTrue();
});

it('does not expose manual approval rule authoring routes anymore', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = approvalRuleManagementUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->postJson('/cumpu/approval-rules', [])
        ->assertStatus(405);

    $this->actingAs($user)
        ->patchJson('/cumpu/approval-rules/test-rule', [])
        ->assertStatus(404);

    $this->actingAs($user)
        ->deleteJson('/cumpu/approval-rules/test-rule')
        ->assertStatus(405);
});
