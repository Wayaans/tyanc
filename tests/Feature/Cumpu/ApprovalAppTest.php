<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\ApprovalAssignment;
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
use Spatie\Activitylog\Models\Activity;

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
            ->has('capabilityOptions.apps'));
});

it('supports dedicated cumpu navigation permissions and keeps approval rule options focused on governable actions', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = cumpuUser([
        PermissionKey::cumpu('dashboard', 'viewany'),
        PermissionKey::cumpu('my_requests', 'viewany'),
        PermissionKey::cumpu('approval_inbox', 'viewany'),
        PermissionKey::cumpu('all_approvals', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'viewany'),
    ]);

    $this->actingAs($user)
        ->get(route('cumpu.dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('cumpu.approvals.my-requests'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('cumpu.approvals.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('cumpu.approvals.all'))
        ->assertOk();

    $response = $this->actingAs($user)
        ->getJson(route('cumpu.approval-rules.index'))
        ->assertOk();

    expect(collect($response->json('capabilityOptions.actions.tyanc.users'))->pluck('permission')->all())
        ->toBe([
            PermissionKey::tyanc('users', 'delete'),
            PermissionKey::tyanc('users', 'import'),
            PermissionKey::tyanc('users', 'suspend'),
            PermissionKey::tyanc('users', 'update'),
        ]);
});

it('allows approval detail access from dedicated cumpu page permissions', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $requester = cumpuUser([
        PermissionKey::cumpu('my_requests', 'viewany'),
    ]);

    $reviewerRole = Role::query()->create([
        'name' => 'Cumpu Page Permission Reviewers',
        'guard_name' => 'web',
        'level' => 70,
    ]);

    $reviewer = cumpuUser([
        PermissionKey::cumpu('approval_inbox', 'viewany'),
    ]);
    $reviewer->assignRole($reviewerRole);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();

    $step = $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Page permission review',
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->create([
            'requested_by_id' => $requester->id,
        ]);

    $approvalRequest->assignments()->create([
        'approval_rule_step_id' => $step->id,
        'step_order_snapshot' => 1,
        'step_label_snapshot' => 'Page permission review',
        'role_name_snapshot' => $reviewerRole->name,
        'assigned_to_id' => $reviewer->id,
        'status' => ApprovalAssignment::StatusPending,
    ]);

    $this->actingAs($requester)
        ->get(route('cumpu.approvals.show', $approvalRequest))
        ->assertOk();

    $this->actingAs($reviewer)
        ->get(route('cumpu.approvals.show', $approvalRequest))
        ->assertOk();

    $outsider = cumpuUser([
        PermissionKey::cumpu('approval_inbox', 'viewany'),
    ]);

    $this->actingAs($outsider)
        ->get(route('cumpu.approvals.show', $approvalRequest))
        ->assertForbidden();
});

it('shows grant lifecycle metrics and recent queues on the cumpu dashboard', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = cumpuUser([
        PermissionKey::cumpu('dashboard', 'viewany'),
        PermissionKey::cumpu('my_requests', 'viewany'),
        PermissionKey::cumpu('approval_inbox', 'viewany'),
        PermissionKey::cumpu('all_approvals', 'viewany'),
        PermissionKey::cumpu('reports', 'viewany'),
    ]);

    $reviewerRole = Role::query()->create([
        'name' => 'Cumpu Dashboard Reviewers',
        'guard_name' => 'web',
        'level' => 70,
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();

    $step = $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Dashboard review',
    ]);

    $inboxApproval = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->create([
            'requested_by_id' => User::factory(),
            'requested_at' => now()->subMinutes(5),
        ]);

    $inboxApproval->assignments()->create([
        'approval_rule_step_id' => $step->id,
        'step_order_snapshot' => 1,
        'step_label_snapshot' => 'Dashboard review',
        'role_name_snapshot' => $reviewerRole->name,
        'assigned_to_id' => $user->id,
        'status' => ApprovalAssignment::StatusPending,
    ]);

    $readyApproval = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->approved()
        ->create([
            'requested_by_id' => $user->id,
            'requested_at' => now()->subMinutes(10),
            'expires_at' => now()->addHour(),
        ]);

    ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->consumed()
        ->create([
            'requested_by_id' => $user->id,
            'requested_at' => now()->subMinutes(20),
            'consumed_by_id' => $user->id,
            'consumed_at' => now()->subMinutes(2),
        ]);

    $expiredApproval = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->approved()
        ->create([
            'requested_by_id' => $user->id,
            'requested_at' => now()->subMinutes(30),
            'expires_at' => now()->subMinute(),
        ]);

    $this->actingAs($user)
        ->getJson(route('cumpu.dashboard'))
        ->assertOk()
        ->assertJsonPath('summary.pending_inbox_count', 1)
        ->assertJsonPath('summary.my_request_count', 3)
        ->assertJsonPath('summary.ready_to_retry_count', 1)
        ->assertJsonPath('summary.consumed_count', 1)
        ->assertJsonPath('summary.expired_count', 1)
        ->assertJsonPath('summary.all_pending_count', 1)
        ->assertJsonPath('recentInbox.0.id', $inboxApproval->id)
        ->assertJsonPath('recentMyRequests.0.id', $readyApproval->id)
        ->assertJsonPath('recentMyRequests.2.id', $expiredApproval->id);

    expect($expiredApproval->fresh()->status)->toBe(ApprovalRequest::StatusExpired);
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
    $this->seed(AppRegistrySeeder::class);

    $manager = cumpuUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
    ]);

    $approverRole = Role::query()->create([
        'name' => 'Cumpu Rule Approver',
        'guard_name' => 'web',
        'level' => 50,
    ]);

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'import' => [
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

    $this->actingAs($manager)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk()
        ->assertJsonPath('summary.created', 1);

    $approvalRule = ApprovalRule::query()
        ->where('permission_name', PermissionKey::tyanc('users', 'import'))
        ->firstOrFail();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role_id' => $approverRole->id, 'label' => 'Import review'],
            ],
            'grant_validity_minutes' => 90,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
        ])
        ->assertOk();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.toggle', $approvalRule), [
            'enabled' => true,
        ])
        ->assertOk();

    expect($approvalRule->fresh()->enabled)->toBeTrue()
        ->and($approvalRule->fresh()->steps()->where('role_id', $approverRole->id)->exists())->toBeTrue();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role_id' => $approverRole->id, 'label' => 'Revised import review'],
            ],
            'grant_validity_minutes' => 180,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
        ])
        ->assertOk();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.toggle', $approvalRule), [
            'enabled' => false,
        ])
        ->assertOk();

    expect($approvalRule->fresh()->enabled)->toBeFalse()
        ->and($approvalRule->fresh()->grant_validity_minutes)->toBe(180)
        ->and($approvalRule->fresh()->steps()->firstOrFail()->label)->toBe('Revised import review');
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

it('marks expired grants in approval history when the request detail is opened', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $requester = cumpuUser([
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->approved()
        ->create([
            'requested_by_id' => $requester->id,
            'expires_at' => now()->subMinute(),
        ]);

    expect(Activity::query()->where('event', 'expired')->exists())->toBeFalse();

    $this->actingAs($requester)
        ->getJson(route('cumpu.approvals.show', $approvalRequest))
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusExpired)
        ->assertJsonPath('history.0.event', 'expired');

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusExpired)
        ->and(Activity::query()->where('event', 'expired')->count())->toBe(1);
});

it('applies tyanc user import approval only when a cumpu-managed rule enables it', function (): void {
    $this->seed(AppRegistrySeeder::class);

    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();
    config()->set('tyanc.features.imports_enabled', true);

    $manager = cumpuUser([
        PermissionKey::cumpu('approval_rules', 'viewany'),
        PermissionKey::cumpu('approval_rules', 'manage'),
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

    config()->set('approval-sot.apps', [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'import' => [
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

    $this->actingAs($manager)
        ->postJson(route('cumpu.approval-rules.sync'))
        ->assertOk();

    $approvalRule = ApprovalRule::query()
        ->where('permission_name', PermissionKey::tyanc('users', 'import'))
        ->firstOrFail();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.update', $approvalRule), [
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'steps' => [
                ['role_id' => $reviewerRole->id, 'label' => 'Import review'],
            ],
            'grant_validity_minutes' => 60,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
        ])
        ->assertOk();

    $this->actingAs($manager)
        ->patchJson(route('cumpu.approval-rules.toggle', $approvalRule), [
            'enabled' => true,
        ])
        ->assertOk();

    $this->actingAs($requester)
        ->postJson(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'users-after-rule.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this import request.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('executed', false)
        ->assertJsonPath('approval.status', ApprovalRequest::StatusPending);

    $approvalRequest = ApprovalRequest::query()->latest('requested_at')->firstOrFail();

    expect($approvalRequest->action)->toBe(PermissionKey::tyanc('users', 'import'))
        ->and($approvalRequest->assignments()->where('assigned_to_id', $reviewer->id)->exists())->toBeTrue();
});
