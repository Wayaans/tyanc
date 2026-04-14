<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\ListApprovalCapabilities;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use App\Support\Permissions\PermissionKey;

function setApprovalCapabilityConfig(array $actions): void
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

it('lists valid approval capabilities from the config source', function (): void {
    setApprovalCapabilityConfig([
        'update' => [
            'mode' => ApprovalMode::Draft->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => false,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role' => 'Reviewers', 'label' => 'User update review'],
            ],
            'grant_validity_minutes' => 60,
            'reminder_after_minutes' => 30,
            'escalation_after_minutes' => 45,
            'conditions' => [
                'changed_fields' => ['email'],
            ],
        ],
    ]);

    $capability = resolve(ListApprovalCapabilities::class)->handle()[0];

    expect($capability->permission_name)->toBe(PermissionKey::tyanc('users', 'update'))
        ->and($capability->mode)->toBe(ApprovalMode::Draft)
        ->and($capability->steps[0]['role_name'])->toBe('Reviewers')
        ->and($capability->grant_validity_minutes)->toBe(60)
        ->and($capability->conditions)->toMatchArray([
            'changed_fields' => ['email'],
        ]);
});

it('supports capability config that only defines mode and runtime management flags', function (): void {
    setApprovalCapabilityConfig([
        'delete' => [
            'mode' => ApprovalMode::Grant->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => false,
        ],
    ]);

    $capability = resolve(ListApprovalCapabilities::class)->handle()[0];

    expect($capability->permission_name)->toBe(PermissionKey::tyanc('users', 'delete'))
        ->and($capability->mode)->toBe(ApprovalMode::Grant)
        ->and($capability->workflow_type)->toBe(ApprovalRule::WorkflowSingle)
        ->and($capability->steps)->toBe([])
        ->and($capability->grant_validity_minutes)->toBe(1440)
        ->and($capability->conditions)->toBeNull();
});

it('rejects capabilities that reference permissions outside permission-sot', function (): void {
    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'imaginary' => [
                    'actions' => [
                        'update' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                ['role' => 'Reviewers', 'label' => 'Imaginary review'],
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

    expect(fn () => resolve(ListApprovalCapabilities::class)->handle())
        ->toThrow(RuntimeException::class, 'permission defined in config/permission-sot.php');
});

it('rejects unsupported approval modes', function (): void {
    setApprovalCapabilityConfig([
        'update' => [
            'mode' => 'queue',
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => false,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role' => 'Reviewers', 'label' => 'User update review'],
            ],
            'grant_validity_minutes' => 60,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
            'conditions' => null,
        ],
    ]);

    expect(fn () => resolve(ListApprovalCapabilities::class)->handle())
        ->toThrow(RuntimeException::class, 'unsupported mode');
});

it('rejects single-step capabilities that define more than one reviewer default', function (): void {
    setApprovalCapabilityConfig([
        'update' => [
            'mode' => ApprovalMode::Grant->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => false,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role' => 'Reviewers', 'label' => 'User update review'],
                ['role' => 'Reviewers', 'label' => 'Second review'],
            ],
            'grant_validity_minutes' => 60,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
            'conditions' => null,
        ],
    ]);

    expect(fn () => resolve(ListApprovalCapabilities::class)->handle())
        ->toThrow(RuntimeException::class, 'cannot define more than one reviewer step');
});

it('rejects orphaned condition definitions', function (): void {
    setApprovalCapabilityConfig([
        'update' => [
            'mode' => ApprovalMode::Grant->value,
            'managed' => true,
            'toggleable' => true,
            'default_enabled' => false,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role' => 'Reviewers', 'label' => 'User update review'],
            ],
            'grant_validity_minutes' => 60,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
            'conditions' => [
                'unknown_gate' => true,
            ],
        ],
    ]);

    expect(fn () => resolve(ListApprovalCapabilities::class)->handle())
        ->toThrow(RuntimeException::class, 'orphaned conditions');
});
