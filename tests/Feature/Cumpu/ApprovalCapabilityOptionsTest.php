<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;

function approvalCapabilityPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function approvalCapabilityUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(approvalCapabilityPermission(...), $permissions));

    return $user;
}

it('returns capability options sourced only from approval-sot', function (): void {
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
                            'workflow_type' => 'single',
                            'steps' => [
                                ['role' => 'Reviewers', 'label' => 'Delete review'],
                            ],
                            'grant_validity_minutes' => 60,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                        'update' => [
                            'mode' => 'draft',
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => 'single',
                            'steps' => [
                                ['role' => 'Reviewers', 'label' => 'Update review'],
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

    $user = approvalCapabilityUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('cumpu.approval-rules.index'))
        ->assertOk();

    expect(collect($response->json('capabilityOptions.actions.tyanc.users'))
        ->pluck('permission')
        ->all())
        ->toBe([
            PermissionKey::tyanc('users', 'delete'),
            PermissionKey::tyanc('users', 'update'),
        ]);
});
