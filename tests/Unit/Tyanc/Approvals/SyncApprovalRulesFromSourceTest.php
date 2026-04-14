<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\SyncApprovalRulesFromSource;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Support\Permissions\PermissionKey;

function setSyncApprovalRulesConfig(array $actions): void
{
    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => $actions,
                ],
            ],
        ],
    ]);
}

it('creates managed approval rules from config and seeds enabled state from default_enabled', function (): void {
    $role = Role::query()->create([
        'name' => 'Sync Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    setSyncApprovalRulesConfig([
        'delete' => [
            'mode' => ApprovalMode::Grant->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => true,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role' => $role->name, 'label' => 'Delete review'],
            ],
            'grant_validity_minutes' => 90,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
            'conditions' => null,
        ],
    ]);

    $result = resolve(SyncApprovalRulesFromSource::class)->handle();
    $rule = ApprovalRule::query()->where('permission_name', PermissionKey::tyanc('users', 'delete'))->firstOrFail();

    expect($result)->toMatchArray([
        'created' => 1,
        'updated' => 0,
        'converted' => 0,
        'retired' => 0,
        'checked' => 0,
        'total' => 1,
    ])
        ->and($rule->managed_by_config)->toBeTrue()
        ->and($rule->enabled)->toBeTrue()
        ->and($rule->mode)->toBe(ApprovalMode::Grant)
        ->and($rule->steps->first()?->role?->name)->toBe($role->name)
        ->and($rule->grant_validity_minutes)->toBe(90);
});

it('preserves runtime enabled state and workflow settings on later syncs', function (): void {
    $role = Role::query()->create([
        'name' => 'Sync Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    $runtimeRole = Role::query()->create([
        'name' => 'Runtime Reviewers',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    setSyncApprovalRulesConfig([
        'delete' => [
            'mode' => ApprovalMode::Grant->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => true,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role' => $role->name, 'label' => 'Delete review'],
            ],
            'grant_validity_minutes' => 90,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
            'conditions' => null,
        ],
    ]);

    resolve(SyncApprovalRulesFromSource::class)->handle();

    $rule = ApprovalRule::query()->firstOrFail();
    $rule->forceFill([
        'enabled' => false,
        'workflow_type' => ApprovalRule::WorkflowMulti,
        'grant_validity_minutes' => 180,
        'reminder_after_minutes' => 15,
        'escalation_after_minutes' => 45,
        'conditions' => [
            'changed_fields' => ['email'],
        ],
    ])->save();

    $rule->steps()->delete();
    $rule->steps()->createMany([
        [
            'role_id' => $runtimeRole->id,
            'step_order' => 1,
            'label' => 'Runtime review',
        ],
        [
            'role_id' => $role->id,
            'step_order' => 2,
            'label' => 'Final runtime review',
        ],
    ]);

    resolve(SyncApprovalRulesFromSource::class)->handle();

    expect($rule->fresh()->enabled)->toBeFalse()
        ->and($rule->fresh()->workflow_type)->toBe(ApprovalRule::WorkflowMulti)
        ->and($rule->fresh()->grant_validity_minutes)->toBe(180)
        ->and($rule->fresh()->reminder_after_minutes)->toBe(15)
        ->and($rule->fresh()->escalation_after_minutes)->toBe(45)
        ->and($rule->fresh()->conditions)->toMatchArray([
            'changed_fields' => ['email'],
        ])
        ->and($rule->fresh()->steps()->orderBy('step_order')->pluck('label')->all())
        ->toBe(['Runtime review', 'Final runtime review']);
});

it('creates managed approval rules from minimal capability config without requiring workflow defaults', function (): void {
    setSyncApprovalRulesConfig([
        'delete' => [
            'mode' => ApprovalMode::Grant->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => true,
        ],
    ]);

    resolve(SyncApprovalRulesFromSource::class)->handle();

    $rule = ApprovalRule::query()->where('permission_name', PermissionKey::tyanc('users', 'delete'))->firstOrFail();

    expect($rule->managed_by_config)->toBeTrue()
        ->and($rule->enabled)->toBeFalse()
        ->and($rule->workflow_type)->toBe(ApprovalRule::WorkflowSingle)
        ->and($rule->grant_validity_minutes)->toBe(1440)
        ->and($rule->steps()->count())->toBe(0);
});

it('retires managed approval rules that are removed from config', function (): void {
    $role = Role::query()->create([
        'name' => 'Sync Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    setSyncApprovalRulesConfig([
        'delete' => [
            'mode' => ApprovalMode::Grant->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => false,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role' => $role->name, 'label' => 'Delete review'],
            ],
            'grant_validity_minutes' => 90,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
            'conditions' => null,
        ],
    ]);

    resolve(SyncApprovalRulesFromSource::class)->handle();

    config()->set('approval-sot.apps', []);

    $result = resolve(SyncApprovalRulesFromSource::class)->handle();
    $rule = ApprovalRule::query()->firstOrFail();

    expect($result['retired'])->toBe(1)
        ->and($rule->fresh()->enabled)->toBeFalse()
        ->and($rule->fresh()->retired_at)->not->toBeNull();
});
