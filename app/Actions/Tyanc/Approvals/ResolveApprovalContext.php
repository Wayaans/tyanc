<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Contracts\Approvals\DraftApprovalSubject;
use App\Data\Cumpu\Approvals\ApprovalContextData;
use App\Data\Cumpu\Approvals\ApprovalContextRequestData;
use App\Data\Cumpu\Approvals\GovernedActionStateData;
use App\Enums\ApprovalMode;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class ResolveApprovalContext
{
    public function __construct(
        private PermissionResourceAccess $access,
        private ResolveApprovalRule $rules,
        private DetectApprovalMode $approvalMode,
        private ShouldBypassApproval $bypassApproval,
    ) {}

    /**
     * @param  array<int, string>  $actionKeys
     * @param  array<int, string>  $governedActionKeys
     * @param  array<string, array<string, mixed>>  $governedActionContexts
     */
    public function handle(
        User $actor,
        string $scopeLabel,
        string $appKey,
        string $resourceKey,
        ?Model $subject = null,
        array $actionKeys = [],
        int $historyLimit = 5,
        array $governedActionKeys = [],
        array $governedActionContexts = [],
    ): ?ApprovalContextData {
        ApprovalRequest::expirePastDueGrants();

        $sanitizedActionKeys = $this->sanitizeKeys($actionKeys);
        $sanitizedGovernedActionKeys = $this->sanitizeKeys($governedActionKeys);

        $pendingCount = $this->baseQuery($appKey, $resourceKey, $subject, $sanitizedActionKeys)
            ->whereIn('status', ApprovalRequest::reviewableStatuses())
            ->count();

        $latestPendingRequest = $this->baseQuery($appKey, $resourceKey, $subject, $sanitizedActionKeys)
            ->whereIn('status', ApprovalRequest::reviewableStatuses())
            ->latest('requested_at')
            ->first();

        $canViewAny = $this->access->handle($actor, PermissionKey::cumpu('all_approvals', 'viewany'))
            || $this->access->handle($actor, PermissionKey::cumpu('approvals', 'viewany'));
        $canViewOwn = $this->access->handle($actor, PermissionKey::cumpu('my_requests', 'viewany'))
            || $this->access->handle($actor, PermissionKey::cumpu('approvals', 'view'));
        $canViewRequests = $canViewAny || $canViewOwn;

        $history = [];

        if ($canViewRequests) {
            $history = $this->baseQuery($appKey, $resourceKey, $subject, $sanitizedActionKeys)
                ->unless(
                    $canViewAny,
                    fn (Builder $query): Builder => $query->where('requested_by_id', $actor->id),
                )
                ->get()
                ->sortByDesc(fn (ApprovalRequest $approvalRequest): int => $this->historyTimestamp($approvalRequest))
                ->take($historyLimit)
                ->values()
                ->map(fn (ApprovalRequest $approvalRequest): ApprovalContextRequestData => ApprovalContextRequestData::fromModel(
                    $approvalRequest,
                    $this->canViewRequest($actor, $approvalRequest),
                ))
                ->all();
        }

        $governedActions = collect($sanitizedGovernedActionKeys)
            ->mapWithKeys(fn (string $actionKey): array => [
                $actionKey => $this->resolveGovernedActionState(
                    actor: $actor,
                    appKey: $appKey,
                    resourceKey: $resourceKey,
                    subject: $subject,
                    actionKey: $actionKey,
                    context: is_array($governedActionContexts[$actionKey] ?? null)
                        ? $governedActionContexts[$actionKey]
                        : [],
                ),
            ])
            ->all();

        if ($pendingCount === 0 && $history === [] && $governedActions === []) {
            return null;
        }

        return new ApprovalContextData(
            scope_label: $scopeLabel,
            pending_count: $pendingCount,
            has_pending_requests: $pendingCount > 0,
            can_view_requests: $canViewRequests,
            latest_pending_request: $latestPendingRequest instanceof ApprovalRequest
                ? ApprovalContextRequestData::fromModel(
                    $latestPendingRequest,
                    $this->canViewRequest($actor, $latestPendingRequest),
                )
                : null,
            history: $history,
            governed_actions: $governedActions,
        );
    }

    /**
     * @param  array<int, string>  $actionKeys
     * @return Builder<ApprovalRequest>
     */
    private function baseQuery(string $appKey, string $resourceKey, ?Model $subject, array $actionKeys): Builder
    {
        $query = ApprovalRequest::query()
            ->with(['requester', 'consumedBy', 'assignments.step'])
            ->where('app_key', $appKey)
            ->where('resource_key', $resourceKey);

        if ($actionKeys !== []) {
            $query->whereIn('action_key', $actionKeys);
        }

        if ($subject instanceof Model && $subject->getKey() !== null) {
            $query
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', (string) $subject->getKey());
        }

        return $query;
    }

    private function canViewRequest(User $actor, ApprovalRequest $approvalRequest): bool
    {
        if ($approvalRequest->requested_by_id === $actor->id && (
            $this->access->handle($actor, PermissionKey::cumpu('my_requests', 'viewany'))
            || $this->access->handle($actor, PermissionKey::cumpu('my_requests', 'view'))
            || $this->access->handle($actor, PermissionKey::cumpu('approvals', 'view'))
        )) {
            return true;
        }

        if ((
            $this->access->handle($actor, PermissionKey::cumpu('approval_inbox', 'viewany'))
            || $this->access->handle($actor, PermissionKey::cumpu('approval_inbox', 'view'))
        ) && $approvalRequest->assignments->contains(
            fn (ApprovalAssignment $assignment): bool => $assignment->assigned_to_id === $actor->id,
        )) {
            return true;
        }

        if ($this->access->handle($actor, PermissionKey::cumpu('all_approvals', 'viewany'))) {
            return true;
        }

        if ($this->access->handle($actor, PermissionKey::cumpu('all_approvals', 'view'))) {
            return true;
        }

        return $this->access->handle($actor, PermissionKey::cumpu('approvals', 'viewany'));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveGovernedActionState(
        User $actor,
        string $appKey,
        string $resourceKey,
        ?Model $subject,
        string $actionKey,
        array $context = [],
    ): GovernedActionStateData {
        $permissionName = PermissionKey::make($appKey, $resourceKey, $actionKey);
        $mode = $this->approvalMode->handle($actor, $permissionName, $subject, $context);
        $rule = $mode !== ApprovalMode::None
            ? $this->rules->handle($actor, $permissionName, $subject, $context)
            : null;
        $approvalEnabled = $mode !== ApprovalMode::None;
        $bypassesForActor = $rule instanceof ApprovalRule && $this->bypassApproval->handle($actor, $rule);

        $usableGrant = $this->relevantRequest($actor, $permissionName, $subject, $mode, 'usable_grant');
        $blockingRequest = $this->relevantRequest($actor, $permissionName, $subject, $mode, 'blocking_request');
        $currentRevisionRequest = $mode === ApprovalMode::Draft
            ? $this->relevantRequest($actor, $permissionName, $subject, $mode, 'current_revision')
            : null;
        $staleApprovedRequest = $mode === ApprovalMode::Draft
            ? $this->relevantRequest($actor, $permissionName, $subject, $mode, 'stale_approved')
            : null;
        $relevantRequest = $usableGrant instanceof ApprovalRequest
            ? $usableGrant
            : ($blockingRequest instanceof ApprovalRequest
                ? $blockingRequest
                : $currentRevisionRequest);

        return new GovernedActionStateData(
            action_key: $actionKey,
            permission_name: $permissionName,
            mode: $mode->value,
            approval_enabled: $approvalEnabled,
            approval_required: $approvalEnabled
                && $mode === ApprovalMode::Grant
                && ! $bypassesForActor
                && ! ($usableGrant instanceof ApprovalRequest)
                && ! ($blockingRequest instanceof ApprovalRequest),
            bypasses_for_actor: $bypassesForActor,
            has_usable_grant: $usableGrant instanceof ApprovalRequest,
            has_blocking_request: $blockingRequest instanceof ApprovalRequest,
            has_committable_draft: $mode === ApprovalMode::Draft && $usableGrant instanceof ApprovalRequest,
            has_stale_subject_revision: $staleApprovedRequest instanceof ApprovalRequest,
            requires_draft_submission: $mode === ApprovalMode::Draft
                && ! $bypassesForActor
                && ! ($usableGrant instanceof ApprovalRequest)
                && ! ($blockingRequest instanceof ApprovalRequest),
            relevant_request: $relevantRequest instanceof ApprovalRequest
                ? ApprovalContextRequestData::fromModel(
                    $relevantRequest,
                    $this->canViewRequest($actor, $relevantRequest),
                )
                : null,
        );
    }

    private function relevantRequest(
        User $actor,
        string $permissionName,
        ?Model $subject,
        ApprovalMode $mode,
        string $kind,
    ): ?ApprovalRequest {
        $query = ApprovalRequest::query()
            ->with(['requester', 'consumedBy', 'assignments.step'])
            ->where('requested_by_id', $actor->id)
            ->where('action', $permissionName)
            ->where('mode', $mode->value)
            ->latest('reviewed_at')
            ->latest('requested_at');

        if ($subject instanceof Model && $subject->getKey() !== null) {
            $query
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', (string) $subject->getKey());
        } else {
            $query
                ->whereNull('subject_type')
                ->whereNull('subject_id');
        }

        $currentRevision = $subject instanceof DraftApprovalSubject
            ? $subject->approvalSubjectRevision()
            : null;

        return match ($kind) {
            'usable_grant' => $this->currentRevisionAwareQuery(clone $query, $mode, $currentRevision)
                ->whereIn('status', ApprovalRequest::consumableStatuses())
                ->where(function (Builder $builder): void {
                    $builder
                        ->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first(),
            'blocking_request' => $this->currentRevisionAwareQuery(clone $query, $mode, $currentRevision)
                ->whereIn('status', ApprovalRequest::reviewableStatuses())
                ->first(),
            'current_revision' => $this->currentRevisionAwareQuery(clone $query, $mode, $currentRevision)->first(),
            'stale_approved' => $mode === ApprovalMode::Draft && $currentRevision !== null
                ? $query
                    ->whereIn('status', ApprovalRequest::consumableStatuses())
                    ->where(function (Builder $builder) use ($currentRevision): void {
                        $builder
                            ->whereNull('subject_revision')
                            ->orWhere('subject_revision', '!=', $currentRevision);
                    })
                    ->first()
                : null,
            default => null,
        };
    }

    /**
     * @param  Builder<ApprovalRequest>  $query
     * @return Builder<ApprovalRequest>
     */
    private function currentRevisionAwareQuery(Builder $query, ApprovalMode $mode, ?string $currentRevision): Builder
    {
        if ($mode !== ApprovalMode::Draft || $currentRevision === null) {
            return $query;
        }

        return $query->where('subject_revision', $currentRevision);
    }

    private function historyTimestamp(ApprovalRequest $approvalRequest): int
    {
        $expiredAt = $approvalRequest->effectiveStatus() === ApprovalRequest::StatusExpired
            ? $approvalRequest->updated_at
            : null;

        return collect([
            $approvalRequest->consumed_at,
            $approvalRequest->cancelled_at,
            $expiredAt,
            $approvalRequest->reviewed_at,
            $approvalRequest->requested_at,
            $approvalRequest->created_at,
        ])
            ->filter(fn (mixed $value): bool => $value instanceof CarbonInterface)
            ->map(fn (CarbonInterface $value): int => $value->getTimestamp())
            ->max() ?? 0;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function sanitizeKeys(array $keys): array
    {
        return collect($keys)
            ->filter(fn (string $key): bool => $key !== '')
            ->unique()
            ->values()
            ->all();
    }
}
