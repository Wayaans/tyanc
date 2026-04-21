<?php

declare(strict_types=1);

use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;

function approvalRuleSyncPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function approvalRuleSyncUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(approvalRuleSyncPermission(...), $permissions));

    return $user;
}

it('flashes shared toast after syncing approval capabilities from the web flow', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewerRole = Role::query()->create([
        'name' => 'Web Sync Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => 'grant',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => 'single',
                            'steps' => [
                                ['role' => $reviewerRole->name, 'label' => 'Delete review'],
                            ],
                            'grant_validity_minutes' => 120,
                            'reminder_after_minutes' => 30,
                            'escalation_after_minutes' => 60,
                            'conditions' => null,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $user = approvalRuleSyncUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->post(route('cumpu.approval-rules.sync'))
        ->assertRedirectToRoute('cumpu.approval-rules.index')
        ->assertSessionHas('toast', fn (array $toast): bool => ($toast['variant'] ?? null) === 'success'
            && ($toast['message'] ?? null) === 'Approval capabilities synced.');
});

it('syncs approval capabilities and toggles managed rules from the screen endpoints', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewerRole = Role::query()->create([
        'name' => 'Sync Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => 'grant',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => 'single',
                            'steps' => [
                                ['role' => $reviewerRole->name, 'label' => 'Delete review'],
                            ],
                            'grant_validity_minutes' => 120,
                            'reminder_after_minutes' => 30,
                            'escalation_after_minutes' => 60,
                            'conditions' => null,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $user = approvalRuleSyncUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk()
        ->assertJsonPath('summary.created', 1)
        ->assertJsonPath('summary.total', 1);

    $approvalRule = ApprovalRule::query()->where('permission_name', PermissionKey::tyanc('users', 'delete'))->firstOrFail();

    expect($approvalRule->managed_by_config)->toBeTrue()
        ->and($approvalRule->enabled)->toBeFalse();

    $this->actingAs($user)
        ->patchJson(route('cumpu.approval-rules.toggle', $approvalRule), [
            'enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('rule.enabled', true);

    expect($approvalRule->fresh()->enabled)->toBeTrue();

    $this->actingAs($user)
        ->getJson(route('cumpu.approval-rules.index'))
        ->assertOk()
        ->assertJsonPath('rules.0.sync_state', 'synced')
        ->assertJsonPath('rules.0.enabled', true)
        ->assertJsonPath('rules.0.is_ready', true);
});

it('updates runtime workflow settings for a synced managed approval rule', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $initialRole = Role::query()->create([
        'name' => 'Initial Reviewers',
        'guard_name' => 'web',
        'level' => 50,
    ]);

    $finalRole = Role::query()->create([
        'name' => 'Final Reviewers',
        'guard_name' => 'web',
        'level' => 70,
    ]);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => 'grant',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => 'single',
                            'steps' => [
                                ['role' => $initialRole->name, 'label' => 'Initial review'],
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

    $user = approvalRuleSyncUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk();

    $approvalRule = ApprovalRule::query()->where('permission_name', PermissionKey::tyanc('users', 'delete'))->firstOrFail();

    $this->actingAs($user)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'workflow_type' => ApprovalRule::WorkflowMulti,
            'grant_validity_minutes' => 180,
            'reminder_after_minutes' => 30,
            'escalation_after_minutes' => 60,
            'steps' => [
                ['role_id' => $initialRole->id, 'label' => 'Department review'],
                ['role_id' => $finalRole->id, 'label' => 'Final review'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('rules.0.workflow_type', ApprovalRule::WorkflowMulti)
        ->assertJsonPath('rules.0.is_ready', true);

    expect($approvalRule->fresh()->workflow_type)->toBe(ApprovalRule::WorkflowMulti)
        ->and($approvalRule->fresh()->grant_validity_minutes)->toBe(180)
        ->and($approvalRule->fresh()->reminder_after_minutes)->toBe(30)
        ->and($approvalRule->fresh()->escalation_after_minutes)->toBe(60)
        ->and($approvalRule->fresh()->steps()->orderBy('step_order')->pluck('label')->all())
        ->toBe(['Department review', 'Final review']);
});

it('accepts one-minute runtime timing values for managed rules', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewerRole = Role::query()->create([
        'name' => 'Fast Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => 'grant',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $user = approvalRuleSyncUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk();

    $approvalRule = ApprovalRule::query()->where('permission_name', PermissionKey::tyanc('users', 'delete'))->firstOrFail();

    $this->actingAs($user)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'grant_validity_minutes' => 1,
            'reminder_after_minutes' => 1,
            'escalation_after_minutes' => 2,
            'steps' => [
                ['role_id' => $reviewerRole->id, 'label' => 'Fast review'],
            ],
        ])
        ->assertOk();

    expect($approvalRule->fresh()->grant_validity_minutes)->toBe(1)
        ->and($approvalRule->fresh()->reminder_after_minutes)->toBe(1)
        ->and($approvalRule->fresh()->escalation_after_minutes)->toBe(2);
});

it('rejects multiple steps for single-step managed workflows', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $firstRole = Role::query()->create([
        'name' => 'Single Step First',
        'guard_name' => 'web',
        'level' => 40,
    ]);

    $secondRole = Role::query()->create([
        'name' => 'Single Step Second',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => 'grant',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $user = approvalRuleSyncUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk();

    $approvalRule = ApprovalRule::query()->where('permission_name', PermissionKey::tyanc('users', 'delete'))->firstOrFail();

    $this->actingAs($user)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'grant_validity_minutes' => 60,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
            'steps' => [
                ['role_id' => $firstRole->id, 'label' => 'First'],
                ['role_id' => $secondRole->id, 'label' => 'Second'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps']);
});

it('shows retired managed rules as removed after the capability is deleted from config', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $reviewerRole = Role::query()->create([
        'name' => 'Removed Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => 'grant',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => 'single',
                            'steps' => [
                                ['role' => $reviewerRole->name, 'label' => 'Delete review'],
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

    $user = approvalRuleSyncUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk();

    config()->set('approval-sot.apps', []);

    $this->actingAs($user)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk()
        ->assertJsonPath('summary.retired', 1);

    $this->actingAs($user)
        ->getJson(route('cumpu.approval-rules.index'))
        ->assertOk()
        ->assertJsonPath('rules.0.sync_state', 'removed')
        ->assertJsonPath('rules.0.enabled', false);
});

it('blocks enabling incomplete managed rules that were synced from minimal config', function (): void {
    $this->seed(AppRegistrySeeder::class);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'delete' => [
                            'mode' => 'grant',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $user = approvalRuleSyncUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $this->actingAs($user)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk();

    $approvalRule = ApprovalRule::query()->where('permission_name', PermissionKey::tyanc('users', 'delete'))->firstOrFail();

    $this->actingAs($user)
        ->patchJson(route('cumpu.approval-rules.toggle', $approvalRule), [
            'enabled' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['approval_rule']);

    $this->actingAs($user)
        ->getJson(route('cumpu.approval-rules.index'))
        ->assertOk()
        ->assertJsonPath('rules.0.sync_state', 'incomplete')
        ->assertJsonPath('rules.0.is_ready', false)
        ->assertJsonPath('rules.0.readiness_issues.0', 'Add at least one reviewer step.');
});
