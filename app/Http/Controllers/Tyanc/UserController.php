<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ResolveApprovalContext;
use App\Actions\Tyanc\Users\CommitUserUpdateDraft;
use App\Actions\Tyanc\Users\DeleteUser;
use App\Actions\Tyanc\Users\ListUsers;
use App\Actions\Tyanc\Users\StoreUser;
use App\Actions\Tyanc\Users\SubmitUserUpdateDraftForApproval;
use App\Actions\Tyanc\Users\SuspendUser;
use App\Actions\Tyanc\Users\UpdateUser;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Data\Tyanc\Imports\ImportRunData;
use App\Data\Tyanc\Rbac\PermissionData;
use App\Data\Tyanc\Rbac\RoleData;
use App\Data\Tyanc\Users\UserFormData;
use App\Data\Tyanc\Users\UserIndexData;
use App\Data\Tyanc\Users\UserUpdateDraftData;
use App\Enums\UserStatus;
use App\Http\Requests\Tyanc\StoreUserRequest;
use App\Http\Requests\Tyanc\UpdateUserRequest;
use App\Http\Requests\Tyanc\UserIndexRequest;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Notifications\FlashToast;
use App\Support\Permissions\PermissionKey;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    public function index(
        UserIndexRequest $request,
        #[CurrentUser] User $user,
        ListUsers $action,
        ResolveApprovalContext $approvalContext,
    ): Response|JsonResponse {
        $payload = [
            'usersTable' => $action->handle($user, $request),
            'recentImports' => $this->recentImports(),
            'approvalRequests' => $this->approvalRequests($user),
            'approvalContext' => $approvalContext->handle(
                actor: $user,
                scopeLabel: __('Users import'),
                appKey: 'tyanc',
                resourceKey: 'users',
                actionKeys: ['import'],
                governedActionKeys: ['import'],
            ),
            'abilities' => [
                'import' => $this->permissionAccess()->handle($user, PermissionKey::tyanc('users', 'import')),
                'export' => $this->permissionAccess()->handle($user, PermissionKey::tyanc('users', 'export')),
                'reviewApprovals' => $this->permissionAccess()->handle($user, PermissionKey::cumpu('approvals', 'viewany')),
            ],
            'features' => [
                'imports_enabled' => (bool) config('tyanc.features.imports_enabled', false),
                'exports_enabled' => (bool) config('tyanc.features.exports_enabled', false),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/users/Index', $payload);
    }

    public function create(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        Gate::forUser($user)->authorize('create', User::class);

        $payload = [
            'user' => UserFormData::defaults(),
            ...$this->formOptions(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/users/Create', $payload);
    }

    public function show(
        Request $request,
        #[CurrentUser] User $actor,
        User $user,
        ResolveApprovalContext $approvalContext,
    ): Response|JsonResponse {
        Gate::forUser($actor)->authorize('view', $user);

        $payload = [
            'user' => UserFormData::fromModel($user),
            'approvalContext' => $approvalContext->handle(
                actor: $actor,
                scopeLabel: $user->name,
                appKey: 'tyanc',
                resourceKey: 'users',
                subject: $user,
                actionKeys: ['update', 'suspend', 'delete'],
                governedActionKeys: ['update', 'delete'],
            ),
            'abilities' => [
                'update' => Gate::forUser($actor)->allows('update', $user),
                'suspend' => Gate::forUser($actor)->allows('suspend', $user),
                'delete' => ! $user->isDeleteProtected() && Gate::forUser($actor)->allows('delete', $user),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/users/Show', $payload);
    }

    public function edit(
        Request $request,
        #[CurrentUser] User $actor,
        User $user,
        ResolveApprovalContext $approvalContext,
    ): Response|JsonResponse {
        Gate::forUser($actor)->authorize('update', $user);

        $currentDraft = $this->currentUserUpdateDraft($actor, $user);
        $updateApprovalContext = $approvalContext->handle(
            actor: $actor,
            scopeLabel: $currentDraft?->approvalSubjectLabel() ?? $user->name,
            appKey: 'tyanc',
            resourceKey: 'users',
            subject: $currentDraft ?? $user,
            actionKeys: ['update'],
            governedActionKeys: ['update'],
        );

        $payload = [
            'user' => UserFormData::fromModel($user),
            'approvalContext' => $approvalContext->handle(
                actor: $actor,
                scopeLabel: $user->name,
                appKey: 'tyanc',
                resourceKey: 'users',
                subject: $user,
                actionKeys: ['delete'],
                governedActionKeys: ['delete'],
            ),
            'updateActionState' => $updateApprovalContext?->governed_actions['update'] ?? null,
            'userUpdateDraft' => $currentDraft instanceof UserUpdateDraft
                ? UserUpdateDraftData::fromModel($currentDraft)
                : null,
            ...$this->formOptions(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/users/Edit', $payload);
    }

    public function store(StoreUserRequest $request, #[CurrentUser] User $user, StoreUser $action): RedirectResponse|JsonResponse
    {
        $managedUser = $action->handle($user, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'user' => UserFormData::fromModel($managedUser),
            ], 201);
        }

        return to_route('tyanc.users.show', $managedUser);
    }

    public function update(UpdateUserRequest $request, #[CurrentUser] User $actor, User $user, UpdateUser $action): RedirectResponse|JsonResponse
    {
        $submission = $action->handle($actor, $user, $request->validated());

        if ($submission['draft'] instanceof UserUpdateDraft) {
            if ($request->wantsJson()) {
                return response()->json([
                    'executed' => false,
                    'mode' => $submission['mode'],
                    'draft' => UserUpdateDraftData::fromModel($submission['draft']),
                ]);
            }

            return back()->with('toast', FlashToast::success(
                __('Draft saved. Submit it for approval when you are ready.'),
            )->toArray());
        }

        if ($submission['approval'] instanceof ApprovalRequest) {
            if ($request->wantsJson()) {
                return response()->json([
                    'executed' => false,
                    'approval' => ApprovalRequestData::fromModel($submission['approval'], $actor),
                ], 202);
            }

            return back()->with('toast', FlashToast::success(
                __('Approval request submitted. Retry the update after it is approved.'),
            )->toArray());
        }

        /** @var User $managedUser */
        $managedUser = $submission['result'];

        if ($request->wantsJson()) {
            return response()->json([
                'user' => UserFormData::fromModel($managedUser),
            ]);
        }

        return to_route('tyanc.users.show', $managedUser);
    }

    public function submitDraft(
        Request $request,
        #[CurrentUser] User $actor,
        User $user,
        SubmitUserUpdateDraftForApproval $action,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'request_note' => ['required', 'string', 'max:1000'],
        ]);

        $approvalRequest = $action->handle(
            actor: $actor,
            user: $user,
            requestNote: is_string($validated['request_note'] ?? null) ? $validated['request_note'] : null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'executed' => false,
                'approval' => ApprovalRequestData::fromModel($approvalRequest, $actor),
            ], 202);
        }

        return back()->with('toast', FlashToast::success(
            __('Draft submitted for approval.'),
        )->toArray());
    }

    public function commitDraft(
        Request $request,
        #[CurrentUser] User $actor,
        User $user,
        CommitUserUpdateDraft $action,
    ): RedirectResponse|JsonResponse {
        $submission = $action->handle($actor, $user);

        /** @var User $managedUser */
        $managedUser = $submission['result'];

        if ($request->wantsJson()) {
            return response()->json([
                'user' => UserFormData::fromModel($managedUser),
            ]);
        }

        return to_route('tyanc.users.show', $managedUser)->with('toast', FlashToast::success(
            __('Approved draft committed.'),
        )->toArray());
    }

    public function suspend(Request $request, #[CurrentUser] User $actor, User $user, SuspendUser $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = $action->handle($actor, $user, $validated);

        if ($submission['approval'] instanceof ApprovalRequest) {
            if ($request->wantsJson()) {
                return response()->json([
                    'approval' => ApprovalRequestData::fromModel($submission['approval'], $actor),
                ], 202);
            }

            return to_route('tyanc.users.show', $user);
        }

        /** @var User $managedUser */
        $managedUser = $submission['result'];

        if ($request->wantsJson()) {
            return response()->json([
                'user' => UserIndexData::fromModel($managedUser),
            ]);
        }

        return to_route('tyanc.users.show', $managedUser);
    }

    public function destroy(Request $request, #[CurrentUser] User $actor, User $user, DeleteUser $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = $action->handle($actor, $user, $validated);

        if ($submission['approval'] instanceof ApprovalRequest) {
            if ($request->wantsJson()) {
                return response()->json([
                    'executed' => false,
                    'approval' => ApprovalRequestData::fromModel($submission['approval'], $actor),
                ], 202);
            }

            return back()->with('toast', FlashToast::success(
                __('Approval request submitted. Retry the deletion after it is approved.'),
            )->toArray());
        }

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('tyanc.users.index');
    }

    /**
     * @return array<int, ImportRunData>
     */
    private function recentImports(): array
    {
        return ImportRun::query()
            ->with(['creator', 'approvalRequests'])
            ->where('type', ImportRun::TypeUsers)
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (ImportRun $importRun): ImportRunData => ImportRunData::fromModel($importRun))
            ->all();
    }

    /**
     * @return array<int, ApprovalRequestData>
     */
    private function approvalRequests(User $actor): array
    {
        if (! $this->permissionAccess()->handle($actor, PermissionKey::cumpu('approvals', 'viewany'))) {
            return [];
        }

        return ApprovalRequest::query()
            ->with(['requester', 'reviewer', 'subject', 'assignments'])
            ->whereHas('assignments', fn ($query) => $query
                ->where('assigned_to_id', $actor->id)
                ->where('status', 'pending'))
            ->latest('requested_at')
            ->limit(6)
            ->get()
            ->map(fn (ApprovalRequest $approvalRequest): ApprovalRequestData => ApprovalRequestData::fromModel($approvalRequest, $actor))
            ->all();
    }

    private function currentUserUpdateDraft(User $actor, User $user): ?UserUpdateDraft
    {
        return UserUpdateDraft::query()
            ->where('user_id', $user->id)
            ->where('created_by_id', $actor->id)
            ->whereNull('committed_at')
            ->latest('updated_at')
            ->first();
    }

    private function permissionAccess(): PermissionResourceAccess
    {
        return resolve(PermissionResourceAccess::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'form' => UserFormData::defaults(),
            'roles' => Role::query()
                ->with('permissions')
                ->withCount(['permissions', 'users'])
                ->orderByDesc('level')
                ->orderBy('name')
                ->get()
                ->map(function (Role $role): array {
                    $data = RoleData::fromModel($role)->toArray();

                    return [
                        'value' => $role->name,
                        'label' => $role->name,
                        'level' => $role->level,
                        'permissions' => $data['permissions'],
                        'permission_count' => $data['permission_count'],
                        'is_reserved' => $data['is_reserved'],
                    ];
                })
                ->values()
                ->all(),
            'permissions' => Permission::query()
                ->with('roles')
                ->withCount('roles')
                ->orderBy('name')
                ->get()
                ->filter(fn (Permission $permission): bool => PermissionKey::existsInSource($permission->name))
                ->map(function (Permission $permission): array {
                    $data = PermissionData::fromModel($permission)->toArray();

                    return [
                        'value' => $permission->name,
                        'label' => $permission->name,
                        'app' => $data['app'],
                        'resource' => $data['resource'],
                        'action' => $data['action'],
                        'is_reserved' => $data['is_reserved'],
                    ];
                })
                ->values()
                ->all(),
            'locales' => Collection::make((array) config('tyanc.supported_locales', []))
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'statuses' => Collection::make(UserStatus::cases())
                ->map(fn (UserStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->value,
                ])
                ->values()
                ->all(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];
    }
}
