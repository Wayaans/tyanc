<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateApprovalProposal
{
    public function __construct(private ResolveApprovers $approvers) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $actor,
        ApprovalRule $rule,
        string $permissionName,
        ?Model $subject = null,
        array $attributes = [],
    ): ApprovalRequest {
        $rule->loadMissing(['steps.role']);

        /** @var ApprovalRuleStep|null $step */
        $step = $rule->steps->sortBy('step_order')->first();

        if (! $step instanceof ApprovalRuleStep) {
            throw ValidationException::withMessages([
                'approval' => __('The approval rule is missing a review step.'),
            ]);
        }

        $approvers = $this->approvers->handle($actor, $rule, $step);

        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => __('No eligible approvers are configured for this approval rule.'),
            ]);
        }

        $parsed = PermissionKey::parse($permissionName);

        if ($parsed === null) {
            throw ValidationException::withMessages([
                'approval' => __('The approval permission is invalid.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $rule, $permissionName, $subject, $attributes, $approvers, $step, $parsed): ApprovalRequest {
            ApprovalRule::query()->whereKey($rule->id)->lockForUpdate()->first();

            if ($subject instanceof Model && $subject->getKey() !== null) {
                $subject->newQuery()
                    ->whereKey($subject->getKey())
                    ->lockForUpdate()
                    ->first();
            } else {
                User::query()->whereKey($actor->id)->lockForUpdate()->first();
            }

            $this->ensureNoActiveRequest($actor, $permissionName, $subject, true);

            $approvalRequest = ApprovalRequest::query()->create([
                'rule_id' => $rule->id,
                'action' => $permissionName,
                'app_key' => $parsed['app'],
                'resource_key' => $parsed['resource'],
                'action_key' => $parsed['action'],
                'status' => ApprovalRequest::StatusPending,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => is_scalar($subject?->getKey()) ? (string) $subject?->getKey() : null,
                'requested_by_id' => $actor->id,
                'request_note' => $this->nullableString($attributes['request_note'] ?? null),
                'payload' => is_array($attributes['payload'] ?? null) ? $attributes['payload'] : null,
                'subject_snapshot' => $this->arrayOrNull($attributes['subject_snapshot'] ?? ($subject?->toArray() ?? null)),
                'before_payload' => $this->arrayOrNull($attributes['before_payload'] ?? null),
                'after_payload' => $this->arrayOrNull($attributes['after_payload'] ?? null),
                'impact_summary' => $this->nullableString($attributes['impact_summary'] ?? null),
                'previous_request_id' => is_string($attributes['previous_request_id'] ?? null)
                    ? $attributes['previous_request_id']
                    : null,
                'expires_at' => $attributes['expires_at'] ?? null,
                'requested_at' => now(),
            ]);

            $approvalRequest->actionRecord()->create([
                'handler' => (string) $attributes['handler'],
                'payload' => is_array($attributes['action_payload'] ?? null) ? $attributes['action_payload'] : null,
            ]);

            $approvers->each(function (User $approver) use ($approvalRequest, $step): void {
                $approvalRequest->assignments()->create([
                    'approval_rule_step_id' => $step->id,
                    'assigned_to_id' => $approver->id,
                    'status' => ApprovalAssignment::StatusPending,
                ]);
            });

            activity('approvals')
                ->performedOn($subject ?? $approvalRequest)
                ->causedBy($actor)
                ->event('requested')
                ->withProperties([
                    'approval_request_id' => (string) $approvalRequest->id,
                    'attributes' => $approvalRequest->toArray(),
                ])
                ->log('Approval requested');

            $approvers->each(fn (User $approver): mixed => $approver->notify(
                new NewApprovalRequestedNotification($approvalRequest)->afterCommit(),
            ));

            return $approvalRequest->fresh([
                'requester',
                'reviewer',
                'cancelledBy',
                'subject',
                'rule.steps.role',
                'actionRecord',
                'assignments.assignee',
            ]);
        });
    }

    private function ensureNoActiveRequest(User $actor, string $permissionName, ?Model $subject, bool $lock = false): void
    {
        $query = ApprovalRequest::query()
            ->where('action', $permissionName)
            ->whereIn('status', ApprovalRequest::activeStatuses());

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($subject instanceof Model) {
            $query
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey());
        } else {
            $query
                ->whereNull('subject_type')
                ->whereNull('subject_id')
                ->where('requested_by_id', $actor->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'approval' => __('An approval request for this action is already pending.'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
