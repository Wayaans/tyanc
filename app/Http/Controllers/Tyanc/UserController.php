<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Users\DeleteUser;
use App\Actions\Tyanc\Users\ListUsers;
use App\Actions\Tyanc\Users\StoreUser;
use App\Actions\Tyanc\Users\SuspendUser;
use App\Actions\Tyanc\Users\UpdateUser;
use App\Data\Tyanc\Users\UserFormData;
use App\Data\Tyanc\Users\UserIndexData;
use App\Enums\UserStatus;
use App\Http\Requests\Tyanc\StoreUserRequest;
use App\Http\Requests\Tyanc\UpdateUserRequest;
use App\Http\Requests\Tyanc\UserIndexRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
    public function index(UserIndexRequest $request, #[CurrentUser] User $user, ListUsers $action): Response|JsonResponse
    {
        $payload = [
            'usersTable' => $action->handle($user, $request),
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

    public function show(Request $request, #[CurrentUser] User $actor, User $user): Response|JsonResponse
    {
        Gate::forUser($actor)->authorize('view', $user);

        $payload = [
            'user' => UserFormData::fromModel($user),
            'abilities' => [
                'update' => Gate::forUser($actor)->allows('update', $user),
                'suspend' => Gate::forUser($actor)->allows('suspend', $user),
                'delete' => Gate::forUser($actor)->allows('delete', $user),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/users/Show', $payload);
    }

    public function edit(Request $request, #[CurrentUser] User $actor, User $user): Response|JsonResponse
    {
        Gate::forUser($actor)->authorize('update', $user);

        $payload = [
            'user' => UserFormData::fromModel($user),
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
        $managedUser = $action->handle($actor, $user, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'user' => UserFormData::fromModel($managedUser),
            ]);
        }

        return to_route('tyanc.users.show', $managedUser);
    }

    public function suspend(Request $request, #[CurrentUser] User $actor, User $user, SuspendUser $action): RedirectResponse|JsonResponse
    {
        $managedUser = $action->handle($actor, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'user' => UserIndexData::fromModel($managedUser),
            ]);
        }

        return to_route('tyanc.users.show', $managedUser);
    }

    public function destroy(Request $request, #[CurrentUser] User $actor, User $user, DeleteUser $action): RedirectResponse|JsonResponse
    {
        $action->handle($actor, $user);

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('tyanc.users.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'form' => UserFormData::defaults(),
            'roles' => Role::query()
                ->orderByDesc('level')
                ->orderBy('name')
                ->get(['name', 'level'])
                ->map(fn (Role $role): array => [
                    'value' => $role->name,
                    'label' => $role->name,
                    'level' => $role->level,
                ])
                ->values()
                ->all(),
            'permissions' => Permission::query()
                ->orderBy('name')
                ->get(['name'])
                ->map(fn (Permission $permission): array => [
                    'value' => $permission->name,
                    'label' => $permission->name,
                ])
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
