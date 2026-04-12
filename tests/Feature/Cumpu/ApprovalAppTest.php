<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function cumpuPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function cumpuUser(array $permissions = []): User
{
    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo(array_map(cumpuPermission(...), $permissions));
    }

    return $user;
}

it('registers cumpu as a standalone app and renders its workspace routes', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = cumpuUser([
        PermissionKey::cumpu('approvals', 'view'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'viewany'),
    ]);

    expect(ApprovalRule::query()->count())->toBe(0)
        ->and(
            App::query()->where('key', 'cumpu')->where('route_prefix', 'cumpu')->where('permission_namespace', 'cumpu')->exists(),
        )->toBeTrue();

    $this->actingAs($user)
        ->get(route('cumpu.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('cumpu/Dashboard')
            ->where('currentApp', 'cumpu')
            ->where('accessibleApps.1.key', 'cumpu')
            ->where('sidebarNavigation.menu.0.href', '/cumpu/dashboard'));

    $this->actingAs($user)
        ->get(route('cumpu.approvals.index'))
        ->assertInertia(fn ($page) => $page
            ->component('cumpu/approvals/Inbox'));

    $this->actingAs($user)
        ->get(route('cumpu.approval-rules.index'))
        ->assertInertia(fn ($page) => $page
            ->component('cumpu/approval-rules/Index')
            ->where('permissionOptions.apps.1.value', 'cumpu'));
});

it('allows legacy tyanc approval permission holders to access the redirected cumpu inbox', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $legacyApprover = cumpuUser([
        PermissionKey::tyanc('approvals', 'viewany'),
    ]);

    $this->actingAs($legacyApprover)
        ->get(route('tyanc.approvals.index'))
        ->assertRedirect(route('cumpu.approvals.index'));

    $this->actingAs($legacyApprover)
        ->get(route('cumpu.approvals.index'))
        ->assertOk();
});

it('allows legacy tyanc approval view holders to open the cumpu my requests workspace', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $legacyViewer = cumpuUser([
        PermissionKey::tyanc('approvals', 'view'),
    ]);

    $this->actingAs($legacyViewer)
        ->get(route('cumpu.approvals.my-requests'))
        ->assertOk();
});

it('manages approval rules through cumpu', function (): void {
    $manager = cumpuUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'create'),
        PermissionKey::cumpu('approval_rules', 'update'),
        PermissionKey::cumpu('approval_rules', 'delete'),
    ]);

    $approverRole = Role::query()->create([
        'name' => 'Cumpu Rule Approver',
        'guard_name' => 'web',
        'level' => 50,
    ]);

    $this->actingAs($manager)
        ->postJson(route('cumpu.approval-rules.store'), [
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'step_role_id' => $approverRole->id,
            'enabled' => true,
        ])
        ->assertCreated();

    $approvalRule = ApprovalRule::query()
        ->where('permission_name', PermissionKey::tyanc('users', 'import'))
        ->firstOrFail();

    expect($approvalRule->enabled)->toBeTrue()
        ->and($approvalRule->steps()->where('role_id', $approverRole->id)->exists())->toBeTrue();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'step_role_id' => $approverRole->id,
            'enabled' => false,
        ])
        ->assertOk();

    expect($approvalRule->fresh()->enabled)->toBeFalse();

    $this->actingAs($manager)
        ->deleteJson(route('cumpu.approval-rules.destroy', $approvalRule))
        ->assertNoContent();

    expect(ApprovalRule::query()->whereKey($approvalRule->id)->exists())->toBeFalse();
});

it('renders approval request detail history and protects the request view', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $requester = cumpuUser([
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $reviewerRole = Role::query()->create([
        'name' => 'Cumpu Detail Reviewers',
        'guard_name' => 'web',
        'level' => 70,
    ]);

    $reviewer = cumpuUser([
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);
    $reviewer->assignRole($reviewerRole);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();

    $step = $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Review import',
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->create([
            'requested_by_id' => $requester->id,
            'payload' => [
                'action_label' => 'Users import',
                'subject_label' => 'users-detail.xlsx',
            ],
        ]);

    $approvalRequest->assignments()->create([
        'approval_rule_step_id' => $step->id,
        'assigned_to_id' => $reviewer->id,
    ]);

    activity('approvals')
        ->performedOn($approvalRequest)
        ->causedBy($requester)
        ->event('requested')
        ->withProperties([
            'approval_request_id' => (string) $approvalRequest->id,
        ])
        ->log('Approval requested');

    $reviewer->notify(new NewApprovalRequestedNotification($approvalRequest));

    expect(
        data_get($reviewer->notifications()->latest()->first()?->data, 'action_url'),
    )->toBe(route('cumpu.approvals.show', $approvalRequest, absolute: false));

    $this->actingAs($requester)
        ->get(route('cumpu.approvals.show', $approvalRequest))
        ->assertInertia(fn ($page) => $page
            ->component('cumpu/approvals/Show')
            ->where('approval.id', (string) $approvalRequest->id)
            ->where('backLink.label', 'My requests')
            ->where('assignments.0.assigned_to_name', $reviewer->name)
            ->where('history.0.event', 'requested'));

    $this->actingAs($reviewer)
        ->getJson(route('cumpu.approvals.show', $approvalRequest))
        ->assertOk()
        ->assertJsonPath('approval.id', (string) $approvalRequest->id)
        ->assertJsonPath('backLink.label', 'Approvals inbox');

    $outsider = cumpuUser([
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $this->actingAs($outsider)
        ->get(route('cumpu.approvals.show', $approvalRequest))
        ->assertForbidden();
});

it('applies tyanc user import approval only when a cumpu-managed rule enables it', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $manager = cumpuUser([
        PermissionKey::cumpu('approval_rules', 'create'),
    ]);
    $requester = cumpuUser([
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $reviewerRole = Role::query()->create([
        'name' => 'Cumpu Reviewers',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    $reviewer = cumpuUser([
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);
    $reviewer->assignRole($reviewerRole);

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'users-before-rule.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('executed', true)
        ->assertJsonPath('approval', null);

    expect(ApprovalRequest::query()->count())->toBe(0);

    $this->actingAs($manager)
        ->postJson(route('cumpu.approval-rules.store'), [
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'step_role_id' => $reviewerRole->id,
            'enabled' => true,
        ])
        ->assertCreated();

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'users-after-rule.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertStatus(202)
        ->assertJsonPath('executed', false)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    expect($approvalRequest->action)->toBe(PermissionKey::tyanc('users', 'import'))
        ->and($approvalRequest->assignments()->where('assigned_to_id', $reviewer->id)->exists())->toBeTrue();
});
