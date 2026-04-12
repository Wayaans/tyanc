<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Roles;

use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Data\Tyanc\Rbac\RoleData;
use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateRole
{
    public function __construct(private SubmitGovernedAction $governedActions) {}

    /**
     * @param  array{name: string, level: int, request_note?: string|null}  $attributes
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool}
     */
    public function handle(User $actor, Role $role, array $attributes): array
    {
        Gate::forUser($actor)->authorize('update', $role);

        $payload = [
            'name' => mb_trim($attributes['name']),
            'level' => (int) $attributes['level'],
            'request_note' => $this->nullableString($attributes['request_note'] ?? null),
        ];

        $this->assertAssignableLevel($actor, $payload['level']);

        return $this->governedActions->handle(
            actor: $actor,
            permissionName: PermissionKey::tyanc('roles', 'update'),
            subject: $role,
            context: [
                ...$payload,
                'changed_fields' => $this->changedFields($role, $payload),
            ],
            definition: [
                'execute' => fn (): Role => $this->apply($actor, $role, $payload),
                'proposal' => [
                    'request_note' => $payload['request_note'],
                    'payload' => [
                        'action_label' => __('Update role'),
                        'subject_label' => $role->approvalSubjectLabel(),
                    ],
                    'subject_snapshot' => $role->approvalSubjectSnapshot(),
                ],
            ],
        );
    }

    /**
     * @param  array{name: string, level: int, request_note?: string|null}  $payload
     */
    private function apply(User $actor, Role $role, array $payload): Role
    {
        $before = RoleData::fromModel($role->fresh(['permissions']))->toArray();

        return DB::transaction(function () use ($actor, $role, $payload, $before): Role {
            $role->forceFill([
                'name' => $payload['name'],
                'level' => $payload['level'],
            ])->save();

            $role->load('permissions');

            activity('rbac')
                ->performedOn($role)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => RoleData::fromModel($role)->toArray(),
                ])
                ->log('Role updated');

            return $role;
        });
    }

    /**
     * @param  array{name: string, level: int, request_note?: string|null}  $attributes
     * @return array<int, string>
     */
    private function changedFields(Role $role, array $attributes): array
    {
        $changedFields = collect();

        if ($attributes['name'] !== $role->name) {
            $changedFields->push('name');
        }

        if ((int) $attributes['level'] !== (int) $role->level) {
            $changedFields->push('level');
        }

        return $changedFields->values()->all();
    }

    private function assertAssignableLevel(User $actor, int $level): void
    {
        if ($actor->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return;
        }

        $actor->loadMissing('roles');
        $actingLevel = $actor->roles->max('level');

        if (! is_numeric($actingLevel) || $level >= (int) $actingLevel) {
            throw new AuthorizationException(__('You cannot assign roles at or above your own hierarchy level.'));
        }
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
