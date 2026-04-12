<?php

declare(strict_types=1);

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function approvalRuleWindowPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function approvalRuleWindowRole(string $name, int $level): Role
{
    /** @var Role $role */
    $role = Role::query()->firstOrCreate(
        [
            'name' => $name,
            'guard_name' => 'web',
        ],
        [
            'level' => $level,
        ],
    );

    $role->forceFill(['level' => $level])->save();

    return $role;
}

function approvalRuleWindowUser(Role $role, array $permissions): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->givePermissionTo(array_map(approvalRuleWindowPermission(...), $permissions));

    return $user;
}

it('uses the approval rule grant validity window when issuing a grant', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalRuleWindowUser(approvalRuleWindowRole('Grant Window Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $reviewerRole = approvalRuleWindowRole('Grant Window Reviewer', 60);
    $reviewer = approvalRuleWindowUser($reviewerRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create([
            'grant_validity_minutes' => 15,
        ]);

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Import review',
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'window-users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this import window.',
        ])
        ->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    expect($approvalRequest->fresh()->expires_at?->toIso8601String())
        ->toBe(now()->addMinutes(15)->toIso8601String());
});

it('consumes an approved grant even if the rule is later disabled', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalRuleWindowUser(approvalRuleWindowRole('Disabled Rule Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $reviewerRole = approvalRuleWindowRole('Disabled Rule Reviewer', 60);
    $reviewer = approvalRuleWindowUser($reviewerRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create([
            'grant_validity_minutes' => 30,
        ]);

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Import review',
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'disabled-rule-users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please approve this import before we disable the rule.',
        ])
        ->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $approvalRule->forceFill([
        'enabled' => false,
    ])->save();

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'disabled-rule-users-retry.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('executed', true)
        ->assertJsonPath('approval', null)
        ->assertJsonPath('import.status', 'queued');

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusConsumed);
});

it('expires used-up windows and requires a new request after the grant window closes', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $requester = approvalRuleWindowUser(approvalRuleWindowRole('Expiry Window Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $reviewerRole = approvalRuleWindowRole('Expiry Window Reviewer', 60);
    $reviewer = approvalRuleWindowUser($reviewerRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create([
            'grant_validity_minutes' => 5,
        ]);

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Import review',
    ]);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'expiry-users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this expiring import.',
        ])
        ->assertStatus(202);

    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    $this->actingAs($reviewer)
        ->patchJson(route('cumpu.approvals.approve', $approvalRequest), [
            'review_note' => 'Approved.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusApproved);

    $this->travel(6)->minutes();

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'expiry-users-retry.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('request_note');

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusExpired);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'expiry-users-resubmit.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please open a new request after expiry.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending);

    expect(ApprovalRequest::query()->where('status', ApprovalRequest::StatusPending)->count())->toBe(1)
        ->and(ApprovalRequest::query()->where('status', ApprovalRequest::StatusExpired)->count())->toBe(1);
});
