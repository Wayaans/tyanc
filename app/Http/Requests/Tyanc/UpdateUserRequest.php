<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Actions\Tyanc\Approvals\ResolveApprovalRule;
use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Rules\ValidEmail;
use App\Support\Permissions\PermissionKey;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? ($this->user()?->can('update', $user) ?? false)
            : false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        assert($user instanceof User);

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(User::class, 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                new ValidEmail,
                Rule::unique(User::class, 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(UserStatus::values())],
            'locale' => ['required', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['required', 'timezone'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists(Role::class, 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $actor = $this->user();
            $subject = $this->route('user');

            if (! $actor instanceof User || ! $subject instanceof User) {
                return;
            }

            $hasAvatarChange = $this->file('avatar') instanceof UploadedFile
                || $this->boolean('remove_avatar');

            if (! $hasAvatarChange) {
                return;
            }

            $rule = resolve(ResolveApprovalRule::class)->handle(
                actor: $actor,
                permissionName: PermissionKey::tyanc('users', 'update'),
                subject: $subject,
                context: $this->approvalContext($subject),
            );

            if ($rule === null) {
                return;
            }

            $message = __('Avatar changes are not available while user updates require approval. Remove the avatar change and retry after approval, or disable approval for this action.');

            if ($this->file('avatar') instanceof UploadedFile) {
                $validator->errors()->add('avatar', $message);
            }

            if ($this->boolean('remove_avatar')) {
                $validator->errors()->add('remove_avatar', $message);
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return;
        }

        $user->loadMissing('roles', 'permissions');

        $defaults = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status->value,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'permissions' => $user->permissions->pluck('name')->values()->all(),
            'remove_avatar' => false,
        ];

        $merged = [];

        foreach ($defaults as $key => $value) {
            if (! $this->has($key)) {
                $merged[$key] = $value;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalContext(User $user): array
    {
        return [
            'changed_fields' => $this->changedFields($user),
            'target_role_levels' => $this->targetRoleLevels($user),
        ];
    }

    /**
     * @return list<string>
     */
    private function changedFields(User $user): array
    {
        $user->loadMissing('roles', 'permissions');

        $changedFields = collect();
        $hasAvatarUpload = $this->file('avatar') instanceof UploadedFile;

        if ($this->string('name')->toString() !== $user->name) {
            $changedFields->push('name');
        }

        if ($this->string('username')->toString() !== $user->username) {
            $changedFields->push('username');
        }

        if ($this->string('email')->toString() !== $user->email) {
            $changedFields->push('email');
        }

        if ($this->input('status', $user->status->value) !== $user->status->value) {
            $changedFields->push('status');
        }

        if ($this->input('locale', $user->locale) !== $user->locale) {
            $changedFields->push('locale');
        }

        if ($this->input('timezone', $user->timezone) !== $user->timezone) {
            $changedFields->push('timezone');
        }

        $nextRoles = collect($this->input('roles', $user->roles->pluck('name')->all()))
            ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
            ->sort()
            ->values()
            ->all();

        $currentRoles = $user->roles->pluck('name')->filter()->sort()->values()->all();

        if ($nextRoles !== $currentRoles) {
            $changedFields->push('roles');
        }

        $nextPermissions = collect($this->input('permissions', $user->permissions->pluck('name')->all()))
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->sort()
            ->values()
            ->all();

        $currentPermissions = $user->permissions->pluck('name')->filter()->sort()->values()->all();

        if ($nextPermissions !== $currentPermissions) {
            $changedFields->push('permissions');
        }

        if ($hasAvatarUpload || ($this->boolean('remove_avatar') && $user->avatar !== null)) {
            $changedFields->push('avatar');
        }

        if (is_string($this->input('password')) && mb_trim($this->input('password')) !== '') {
            $changedFields->push('password');
        }

        return $changedFields->unique()->values()->all();
    }

    /**
     * @return list<int>
     */
    private function targetRoleLevels(User $user): array
    {
        $user->loadMissing('roles');

        $roleNames = collect($this->input('roles', $user->roles->pluck('name')->all()))
            ->filter(fn (mixed $role): bool => is_string($role) && mb_trim($role) !== '')
            ->map(fn (string $role): string => mb_trim($role))
            ->unique()
            ->values()
            ->all();

        if ($roleNames === []) {
            return [];
        }

        return Role::query()
            ->whereIn('name', $roleNames)
            ->pluck('level')
            ->filter(fn (mixed $level): bool => is_numeric($level))
            ->map(fn (mixed $level): int => (int) $level)
            ->values()
            ->all();
    }
}
