<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Roles;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ResolveApprovalRule;
use App\Actions\Tyanc\Approvals\ShouldBypassApproval;
use App\Data\Cumpu\Approvals\ApprovalContextRequestData;
use App\Data\Cumpu\Approvals\GovernedActionStateData;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Rbac\RoleData;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use App\Support\Tables\AppliesTableQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class ListRoles
{
    public function __construct(
        private AppliesTableQuery $tableQuery,
        private ResolveApprovalRule $rules,
        private ShouldBypassApproval $bypassApproval,
        private PermissionResourceAccess $access,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>
     * }
     */
    public function handle(User $actor, DataTableQueryData $query): array
    {
        Gate::forUser($actor)->authorize('viewAny', Role::class);

        /** @var Collection<int, Role> $roles */
        $roles = Role::query()
            ->with('permissions')
            ->withCount(['permissions', 'users'])
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        $updateApprovalStates = $this->updateApprovalStates($actor, $roles);
        $rows = $roles->map(
            fn (Role $role): array => RoleData::fromModel(
                $role,
                $updateApprovalStates[(int) $role->getKey()] ?? null,
            )->toArray(),
        );

        return [
            ...$this->tableQuery->handle(
                items: $rows,
                query: $query,
                sorts: [
                    'name' => 'name',
                    'level' => 'level',
                    'permission_count' => 'permission_count',
                    'user_count' => 'user_count',
                    'created_at' => 'created_at',
                ],
                filters: [
                    'search' => fn (array $row, mixed $value): bool => $this->matchesSearch($row, $value),
                    'reserved' => fn (array $row, mixed $value): bool => $this->matchesReserved($row, $value),
                ],
            ),
            'filters' => $this->filters(),
        ];
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return array<int, GovernedActionStateData>
     */
    private function updateApprovalStates(User $actor, Collection $roles): array
    {
        if ($roles->isEmpty()) {
            return [];
        }

        $permissionName = PermissionKey::tyanc('roles', 'update');
        $sampleRole = $roles->first();
        $rule = $this->rules->handle($actor, $permissionName, $sampleRole);
        $approvalEnabled = $rule instanceof ApprovalRule;
        $bypassesForActor = $approvalEnabled && $this->bypassApproval->handle($actor, $rule);
        $canViewDetails = $this->access->handle($actor, PermissionKey::cumpu('approvals', 'viewany'))
            || $this->access->handle($actor, PermissionKey::cumpu('approvals', 'view'));

        $subjectType = $sampleRole->getMorphClass();
        $roleIds = $roles
            ->map(fn (Role $role): string => (string) $role->getKey())
            ->values()
            ->all();

        /** @var Collection<string, Collection<int, ApprovalRequest>> $requestsBySubject */
        $requestsBySubject = ApprovalRequest::query()
            ->with(['requester', 'consumedBy', 'assignments.step'])
            ->where('requested_by_id', $actor->id)
            ->where('action', $permissionName)
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $roleIds)
            ->latest('reviewed_at')
            ->latest('requested_at')
            ->get()
            ->groupBy(fn (ApprovalRequest $approvalRequest): string => (string) $approvalRequest->subject_id);

        return $roles
            ->mapWithKeys(function (Role $role) use ($permissionName, $approvalEnabled, $bypassesForActor, $canViewDetails, $requestsBySubject): array {
                /** @var Collection<int, ApprovalRequest> $requests */
                $requests = $requestsBySubject->get((string) $role->getKey(), collect());
                $usableGrant = $requests->first(
                    fn (ApprovalRequest $approvalRequest): bool => $approvalRequest->isGrantConsumable(),
                );
                $blockingRequest = $requests->first(
                    fn (ApprovalRequest $approvalRequest): bool => in_array(
                        $approvalRequest->effectiveStatus(),
                        ApprovalRequest::reviewableStatuses(),
                        true,
                    ),
                );
                $relevantRequest = $usableGrant instanceof ApprovalRequest
                    ? $usableGrant
                    : $blockingRequest;

                return [
                    (int) $role->getKey() => new GovernedActionStateData(
                        action_key: 'update',
                        permission_name: $permissionName,
                        mode: $approvalEnabled ? ApprovalMode::Grant->value : ApprovalMode::None->value,
                        approval_enabled: $approvalEnabled,
                        approval_required: $approvalEnabled
                            && ! $bypassesForActor
                            && ! ($usableGrant instanceof ApprovalRequest)
                            && ! ($blockingRequest instanceof ApprovalRequest),
                        bypasses_for_actor: $bypassesForActor,
                        has_usable_grant: $usableGrant instanceof ApprovalRequest,
                        has_blocking_request: $blockingRequest instanceof ApprovalRequest,
                        has_committable_draft: false,
                        has_stale_subject_revision: false,
                        requires_draft_submission: false,
                        relevant_request: $relevantRequest instanceof ApprovalRequest
                            ? ApprovalContextRequestData::fromModel($relevantRequest, $canViewDetails)
                            : null,
                    ),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesSearch(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        $search = mb_strtolower(mb_trim((string) $value));

        if ($search === '') {
            return true;
        }

        return str_contains(mb_strtolower((string) $row['name']), $search)
            || collect(is_array($row['permissions'] ?? null) ? $row['permissions'] : [])->contains(
                fn (mixed $permission): bool => is_string($permission) && str_contains(mb_strtolower($permission), $search),
            );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesReserved(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        return match ((string) $value) {
            'reserved' => (bool) ($row['is_reserved'] ?? false),
            'custom' => ! (bool) ($row['is_reserved'] ?? false),
            default => true,
        };
    }

    /**
     * @return array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'search',
                'label' => 'Roles',
                'type' => 'text',
                'placeholder' => 'Search roles',
            ],
            [
                'id' => 'reserved',
                'label' => 'Type',
                'type' => 'select',
                'options' => [
                    ['label' => 'All roles', 'value' => 'all'],
                    ['label' => 'Reserved', 'value' => 'reserved'],
                    ['label' => 'Custom', 'value' => 'custom'],
                ],
            ],
        ];
    }
}
