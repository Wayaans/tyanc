<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Contracts\Approvals\ApprovalSubject;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        ApprovalRequest::expirePastDueGrants();
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

        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        if ($requestNote === null) {
            throw ValidationException::withMessages([
                'request_note' => __('Provide a reason before requesting approval.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $rule, $permissionName, $subject, $attributes, $approvers, $step, $parsed, $requestNote): ApprovalRequest {
            ApprovalRule::query()->whereKey($rule->id)->lockForUpdate()->first();

            if ($subject instanceof Model && $subject->getKey() !== null) {
                $subject->newQuery()
                    ->whereKey($subject->getKey())
                    ->lockForUpdate()
                    ->first();
            } else {
                User::query()->whereKey($actor->id)->lockForUpdate()->first();
            }

            $this->ensureNoBlockingRequest($actor, $permissionName, $subject, true);

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
                'request_note' => $requestNote,
                'payload' => $this->proposalPayload($attributes, $permissionName, $subject),
                'subject_snapshot' => $this->arrayOrNull($attributes['subject_snapshot'] ?? $this->subjectSnapshot($subject)),
                'requested_at' => now(),
            ]);

            $approvers->each(function (User $approver) use ($approvalRequest, $step): void {
                $approvalRequest->assignments()->create([
                    'approval_rule_step_id' => $step->id,
                    'step_order_snapshot' => $step->step_order,
                    'step_label_snapshot' => $step->label,
                    'role_name_snapshot' => $step->role?->name,
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
                'consumedBy',
                'subject',
                'rule.steps.role',
                'assignments.assignee',
            ]);
        });
    }

    private function ensureNoBlockingRequest(User $actor, string $permissionName, ?Model $subject, bool $lock = false): void
    {
        $query = ApprovalRequest::query()
            ->where('requested_by_id', $actor->id)
            ->where('action', $permissionName)
            ->where(function ($builder): void {
                $builder
                    ->whereIn('status', ApprovalRequest::reviewableStatuses())
                    ->orWhere(function ($approvedBuilder): void {
                        $approvedBuilder
                            ->whereIn('status', ApprovalRequest::consumableStatuses())
                            ->where(function ($grantBuilder): void {
                                $grantBuilder
                                    ->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($subject instanceof Model) {
            $query
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', (string) $subject->getKey());
        } else {
            $query
                ->whereNull('subject_type')
                ->whereNull('subject_id');
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'approval' => __('An approval request for this action is already active.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{action_label: string, subject_label: string}
     */
    private function proposalPayload(array $attributes, string $permissionName, ?Model $subject): array
    {
        $payload = is_array($attributes['payload'] ?? null) ? $attributes['payload'] : [];

        $actionLabel = $this->nullableString($payload['action_label'] ?? null)
            ?? Str::of($permissionName)->replace(['.', '_'], ' ')->title()->value();
        $subjectLabel = $this->nullableString($payload['subject_label'] ?? null)
            ?? $this->subjectLabel($subject)
            ?? __('Approval request');

        return [
            'action_label' => $actionLabel,
            'subject_label' => $subjectLabel,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subjectSnapshot(?Model $subject): ?array
    {
        if ($subject instanceof ApprovalSubject) {
            return $subject->approvalSubjectSnapshot();
        }

        return $subject?->toArray();
    }

    private function subjectLabel(?Model $subject): ?string
    {
        if ($subject instanceof ApprovalSubject) {
            return $subject->approvalSubjectLabel();
        }

        if ($subject instanceof Model && $subject->getKey() !== null) {
            return sprintf('%s #%s', class_basename($subject), (string) $subject->getKey());
        }

        return null;
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
