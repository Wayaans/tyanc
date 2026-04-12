<?php

declare(strict_types=1);

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

function reportPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function reportRole(string $name, int $level): Role
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

function reportUser(Role $role, array $permissions = []): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    if ($permissions !== []) {
        $user->givePermissionTo(array_map(reportPermission(...), $permissions));
    }

    return $user;
}

function createReportApproval(string $status, array $attributes = []): ApprovalRequest
{
    $appKey = $attributes['app_key'] ?? 'tyanc';
    $resourceKey = $attributes['resource_key'] ?? 'users';
    $actionKey = $attributes['action_key'] ?? 'import';
    $permissionName = PermissionKey::make($appKey, $resourceKey, $actionKey);

    $requester = $attributes['requester'] ?? reportUser(reportRole('Reports Requester '.fake()->unique()->word(), 10), [
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $reviewerRole = $attributes['reviewer_role'] ?? reportRole('Reports Reviewer '.fake()->unique()->word(), 50);
    $reviewer = $attributes['reviewer'] ?? reportUser($reviewerRole, [
        $permissionName,
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $rule = ApprovalRule::factory()
        ->forPermission($permissionName)
        ->enabled()
        ->create([
            'reminder_after_minutes' => $attributes['reminder_after_minutes'] ?? 30,
            'escalation_after_minutes' => $attributes['escalation_after_minutes'] ?? 60,
        ]);

    $step = $rule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Report review',
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->for($rule, 'rule')
        ->create([
            'status' => $status,
            'action' => $permissionName,
            'app_key' => $appKey,
            'resource_key' => $resourceKey,
            'action_key' => $actionKey,
            'requested_by_id' => $requester->id,
            'reviewed_by_id' => in_array($status, [ApprovalRequest::StatusApproved, ApprovalRequest::StatusRejected, ApprovalRequest::StatusConsumed], true)
                ? $reviewer->id
                : null,
            'reviewed_at' => in_array($status, [ApprovalRequest::StatusApproved, ApprovalRequest::StatusRejected, ApprovalRequest::StatusConsumed], true)
                ? now()->subMinutes(30)
                : null,
            'requested_at' => $attributes['requested_at'] ?? now()->subHours(2),
            'cancelled_by_id' => $status === ApprovalRequest::StatusCancelled
                ? ($attributes['cancelled_by_id'] ?? $requester->id)
                : null,
            'cancelled_at' => $status === ApprovalRequest::StatusCancelled
                ? ($attributes['cancelled_at'] ?? now()->subMinutes(15))
                : null,
            'expires_at' => $attributes['expires_at'] ?? null,
            'escalated_at' => $attributes['escalated_at'] ?? null,
            'last_reassigned_at' => $attributes['last_reassigned_at'] ?? null,
            'consumed_by_id' => $status === ApprovalRequest::StatusConsumed
                ? ($attributes['consumed_by_id'] ?? $requester->id)
                : null,
            'consumed_at' => $status === ApprovalRequest::StatusConsumed
                ? ($attributes['consumed_at'] ?? now()->subMinutes(10))
                : null,
        ]);

    if (in_array($status, ApprovalRequest::activeStatuses(), true)) {
        $approvalRequest->assignments()->create([
            'approval_rule_step_id' => $step->id,
            'step_order_snapshot' => 1,
            'step_label_snapshot' => 'Report review',
            'role_name_snapshot' => $reviewerRole->name,
            'assigned_to_id' => $reviewer->id,
            'status' => 'pending',
            'created_at' => $attributes['assignment_created_at'] ?? now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
    }

    return $approvalRequest;
}

it('renders approval reports with summary metrics and filters overdue rows', function (): void {
    $reportViewer = reportUser(reportRole('Reports Viewer', 80), [
        PermissionKey::cumpu('reports', 'viewany'),
        PermissionKey::cumpu('reports', 'export'),
    ]);

    $overdueApproval = createReportApproval(ApprovalRequest::StatusPending, [
        'action_key' => 'import',
        'requested_at' => now()->subHours(3),
        'assignment_created_at' => now()->subHours(3),
        'escalated_at' => now()->subMinutes(30),
        'last_reassigned_at' => now()->subMinutes(20),
    ]);

    createReportApproval(ApprovalRequest::StatusInReview, [
        'action_key' => 'sync',
        'requested_at' => now()->subMinutes(20),
        'assignment_created_at' => now()->subMinutes(20),
    ]);

    createReportApproval(ApprovalRequest::StatusApproved, [
        'action_key' => 'export',
        'requested_at' => now()->subHours(4),
    ]);

    createReportApproval(ApprovalRequest::StatusRejected, [
        'resource_key' => 'files',
        'action_key' => 'upload',
        'requested_at' => now()->subHours(5),
    ]);

    createReportApproval(ApprovalRequest::StatusCancelled, [
        'resource_key' => 'roles',
        'action_key' => 'archive',
        'requested_at' => now()->subHours(5)->subMinutes(30),
    ]);

    createReportApproval(ApprovalRequest::StatusApproved, [
        'action_key' => 'grant',
        'requested_at' => now()->subHours(6),
        'expires_at' => now()->subMinute(),
    ]);

    $consumedApproval = createReportApproval(ApprovalRequest::StatusConsumed, [
        'action_key' => 'delete',
        'requested_at' => now()->subHours(7),
    ]);

    $this->actingAs($reportViewer)
        ->get(route('cumpu.approvals.reports.index'))
        ->assertInertia(fn ($page) => $page
            ->component('cumpu/approvals/Reports')
            ->where('summary.total', 7)
            ->where('summary.pending', 1)
            ->where('summary.in_review', 1)
            ->where('summary.approved', 1)
            ->where('summary.consumed', 1)
            ->where('summary.rejected', 1)
            ->where('summary.cancelled', 1)
            ->where('summary.expired', 1)
            ->where('summary.overdue', 1)
            ->where('summary.escalated', 1)
            ->where('summary.reassigned', 1));

    $this->actingAs($reportViewer)
        ->getJson(route('cumpu.approvals.reports.index', [
            'filter' => ['overdue' => '1'],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', $overdueApproval->id)
        ->assertJsonPath('rows.0.is_overdue', true);

    $this->actingAs($reportViewer)
        ->getJson(route('cumpu.approvals.reports.index', [
            'filter' => ['status' => ApprovalRequest::StatusConsumed],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', $consumedApproval->id)
        ->assertJsonPath('rows.0.status', ApprovalRequest::StatusConsumed)
        ->assertJsonPath('rows.0.consumed_by_name', $consumedApproval->requester->name);
});

it('exports filtered approval report rows', function (): void {
    config()->set('tyanc.features.exports_enabled', true);

    $reportViewer = reportUser(reportRole('Reports Exporter', 85), [
        PermissionKey::cumpu('reports', 'viewany'),
        PermissionKey::cumpu('reports', 'export'),
    ]);

    createReportApproval(ApprovalRequest::StatusApproved, [
        'action_key' => 'export',
        'requested_at' => now()->subHours(2),
    ]);

    $this->actingAs($reportViewer)
        ->get(route('cumpu.approvals.reports.export', [
            'filter' => ['status' => ApprovalRequest::StatusApproved],
        ]))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=approval-requests-report.xlsx');
});
