<?php

declare(strict_types=1);

use App\Actions\Tyanc\Approvals\FindOverdueApprovals;
use App\Jobs\SendApprovalEscalation;
use App\Jobs\SendApprovalReminder;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalEscalatedNotification;
use App\Notifications\ApprovalReminderNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function escalationPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function escalationRole(string $name, int $level): Role
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

function escalationUser(Role $role, array $permissions = []): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    if ($permissions !== []) {
        $user->givePermissionTo(array_map(escalationPermission(...), $permissions));
    }

    return $user;
}

function overdueApprovalRequest(): array
{
    $requester = escalationUser(escalationRole('Escalation Requester', 10), [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'view'),
    ]);

    $approverRole = escalationRole('Escalation Approver', 50);
    $approver = escalationUser($approverRole, [
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'approve'),
    ]);

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create([
            'reminder_after_minutes' => 30,
            'escalation_after_minutes' => 60,
        ]);

    $step = $approvalRule->steps()->create([
        'role_id' => $approverRole->id,
        'step_order' => 1,
        'label' => 'Reminder review',
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->create([
            'requested_by_id' => $requester->id,
            'requested_at' => now(),
        ]);

    $approvalRequest->assignments()->create([
        'approval_rule_step_id' => $step->id,
        'step_order_snapshot' => 1,
        'step_label_snapshot' => 'Reminder review',
        'role_name_snapshot' => $approverRole->name,
        'assigned_to_id' => $approver->id,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$approvalRequest, $requester, $approver];
}

it('queues reminder and escalation jobs for overdue approvals', function (): void {
    Queue::fake();

    [$approvalRequest] = overdueApprovalRequest();

    $this->travel(61)->minutes();

    $this->artisan('approvals:dispatch-escalations')
        ->assertSuccessful();

    Queue::assertPushed(SendApprovalReminder::class, fn (SendApprovalReminder $job): bool => $job->approvalRequestId === $approvalRequest->id);
    Queue::assertPushed(SendApprovalEscalation::class, fn (SendApprovalEscalation $job): bool => $job->approvalRequestId === $approvalRequest->id);
});

it('sends reminder and escalation notifications when overdue jobs are processed', function (): void {
    Notification::fake();

    [$approvalRequest, $requester, $approver] = overdueApprovalRequest();

    $this->travel(61)->minutes();

    $finder = resolve(FindOverdueApprovals::class);

    new SendApprovalReminder($approvalRequest->id)->handle($finder);
    new SendApprovalEscalation($approvalRequest->id)->handle($finder);

    Notification::assertSentTo(
        $approver,
        ApprovalReminderNotification::class,
        function (ApprovalReminderNotification $notification) use ($approver): bool {
            $payload = $notification->toArray($approver);

            return data_get($payload, 'approval_status') === ApprovalRequest::StatusPending
                && str_contains((string) data_get($payload, 'body'), 'retry Users import for users.xlsx once');
        },
    );
    Notification::assertSentTo(
        $approver,
        ApprovalEscalatedNotification::class,
        function (ApprovalEscalatedNotification $notification) use ($approver): bool {
            $payload = $notification->toArray($approver);

            return data_get($payload, 'approval_status') === ApprovalRequest::StatusPending
                && str_contains((string) data_get($payload, 'body'), 'This request is overdue.');
        },
    );
    Notification::assertSentTo(
        $requester,
        ApprovalEscalatedNotification::class,
        function (ApprovalEscalatedNotification $notification) use ($requester): bool {
            $payload = $notification->toArray($requester);

            return data_get($payload, 'approval_status') === ApprovalRequest::StatusPending
                && str_contains((string) data_get($payload, 'body'), 'retry Users import for users.xlsx once');
        },
    );

    expect($approvalRequest->fresh()->last_reminded_at)->not->toBeNull()
        ->and($approvalRequest->fresh()->escalated_at)->not->toBeNull();
});
