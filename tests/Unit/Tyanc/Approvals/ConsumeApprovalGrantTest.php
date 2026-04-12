<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\ConsumeApprovalGrant;
use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

it('consumes a matching approved grant once', function (): void {
    $actor = User::factory()->create();
    $subject = App::factory()->create([
        'key' => 'erp',
        'label' => 'ERP Workspace',
        'route_prefix' => 'erp',
        'permission_namespace' => 'erp',
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->approved()
        ->create([
            'rule_id' => null,
            'action' => PermissionKey::tyanc('apps', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'apps',
            'action_key' => 'update',
            'requested_by_id' => $actor->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->id,
            'expires_at' => now()->addMinutes(30),
        ]);

    $executions = 0;

    $result = resolve(ConsumeApprovalGrant::class)->handle(
        actor: $actor,
        permissionName: PermissionKey::tyanc('apps', 'update'),
        subject: $subject,
        execute: function () use (&$executions): string {
            $executions++;

            return 'updated';
        },
    );

    expect($result['consumed'])->toBeTrue()
        ->and($result['result'])->toBe('updated')
        ->and($result['approval'])->toBeInstanceOf(ApprovalRequest::class)
        ->and($executions)->toBe(1)
        ->and($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed)
        ->and($approvalRequest->fresh()->consumed_by_id)->toBe($actor->id)
        ->and($approvalRequest->fresh()->consumed_at)->not->toBeNull();
});

it('marks expired grants as expired instead of consuming them', function (): void {
    $actor = User::factory()->create();
    $subject = App::factory()->create([
        'key' => 'tasks',
        'label' => 'Tasks Workspace',
        'route_prefix' => 'tasks',
        'permission_namespace' => 'tasks',
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->approved()
        ->create([
            'rule_id' => null,
            'action' => PermissionKey::tyanc('apps', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'apps',
            'action_key' => 'update',
            'requested_by_id' => $actor->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->id,
            'expires_at' => now()->subMinute(),
        ]);

    $executions = 0;

    $result = resolve(ConsumeApprovalGrant::class)->handle(
        actor: $actor,
        permissionName: PermissionKey::tyanc('apps', 'update'),
        subject: $subject,
        execute: function () use (&$executions): string {
            $executions++;

            return 'updated';
        },
    );

    expect($result['consumed'])->toBeFalse()
        ->and($result['approval'])->toBeNull()
        ->and($result['result'])->toBeNull()
        ->and($executions)->toBe(0)
        ->and($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusExpired)
        ->and($approvalRequest->fresh()->consumed_at)->toBeNull();
});

it('ignores approved grants that belong to another requester or subject', function (): void {
    $actor = User::factory()->create();
    $otherActor = User::factory()->create();
    $subject = App::factory()->create([
        'key' => 'inventory',
        'label' => 'Inventory',
        'route_prefix' => 'inventory',
        'permission_namespace' => 'inventory',
    ]);
    $otherSubject = App::factory()->create([
        'key' => 'crm',
        'label' => 'CRM',
        'route_prefix' => 'crm',
        'permission_namespace' => 'crm',
    ]);

    $foreignRequesterGrant = ApprovalRequest::factory()
        ->approved()
        ->create([
            'rule_id' => null,
            'action' => PermissionKey::tyanc('apps', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'apps',
            'action_key' => 'update',
            'requested_by_id' => $otherActor->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->id,
            'expires_at' => now()->addMinutes(30),
        ]);

    $foreignSubjectGrant = ApprovalRequest::factory()
        ->approved()
        ->create([
            'rule_id' => null,
            'action' => PermissionKey::tyanc('apps', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'apps',
            'action_key' => 'update',
            'requested_by_id' => $actor->id,
            'subject_type' => $otherSubject->getMorphClass(),
            'subject_id' => (string) $otherSubject->id,
            'expires_at' => now()->addMinutes(30),
        ]);

    $executions = 0;

    $result = resolve(ConsumeApprovalGrant::class)->handle(
        actor: $actor,
        permissionName: PermissionKey::tyanc('apps', 'update'),
        subject: $subject,
        execute: function () use (&$executions): string {
            $executions++;

            return 'updated';
        },
    );

    expect($result['consumed'])->toBeFalse()
        ->and($result['approval'])->toBeNull()
        ->and($result['result'])->toBeNull()
        ->and($executions)->toBe(0)
        ->and($foreignRequesterGrant->fresh()->status)->toBe(ApprovalRequest::StatusApproved)
        ->and($foreignSubjectGrant->fresh()->status)->toBe(ApprovalRequest::StatusApproved);
});
