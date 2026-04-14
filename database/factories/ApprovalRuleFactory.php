<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRule>
 */
final class ApprovalRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'permission_name' => PermissionKey::tyanc('users', 'import'),
            'enabled' => false,
            'mode' => ApprovalMode::Grant->value,
            'managed_by_config' => false,
            'source_key' => null,
            'config_hash' => null,
            'retired_at' => null,
            'retired_reason' => null,
            'workflow_type' => ApprovalRule::WorkflowSingle,
            'conditions' => null,
            'grant_validity_minutes' => 1440,
            'reminder_after_minutes' => null,
            'escalation_after_minutes' => null,
        ];
    }

    public function enabled(): self
    {
        return $this->state(fn (): array => [
            'enabled' => true,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => [
            'enabled' => false,
        ]);
    }

    public function managed(?string $sourceKey = null): self
    {
        return $this->state(fn (array $attributes): array => [
            'managed_by_config' => true,
            'source_key' => $sourceKey ?? (string) ($attributes['permission_name'] ?? null),
            'config_hash' => hash('sha256', fake()->uuid()),
        ]);
    }

    public function draftMode(): self
    {
        return $this->state(fn (): array => [
            'mode' => ApprovalMode::Draft->value,
        ]);
    }

    public function forPermission(string $permissionName): self
    {
        return $this->state(function () use ($permissionName): array {
            $parsed = PermissionKey::parse($permissionName);

            return [
                'app_key' => $parsed['app'] ?? 'tyanc',
                'resource_key' => $parsed['resource'] ?? 'users',
                'action_key' => $parsed['action'] ?? 'viewany',
                'permission_name' => $permissionName,
                'source_key' => $permissionName,
            ];
        });
    }

    public function withRoleStep(?Role $role = null, int $stepOrder = 1): self
    {
        return $this->afterCreating(function (ApprovalRule $rule) use ($role, $stepOrder): void {
            $resolvedRole = $role;

            if (! $resolvedRole instanceof Role) {
                /** @var Role|null $existingRole */
                $existingRole = Role::query()->first();

                $resolvedRole = $existingRole instanceof Role
                    ? $existingRole
                    : Role::query()->create([
                        'name' => sprintf('Approver %s', fake()->unique()->word()),
                        'guard_name' => 'web',
                        'level' => fake()->numberBetween(10, 100),
                    ]);
            }

            $rule->steps()->create([
                'role_id' => $resolvedRole->id,
                'step_order' => $stepOrder,
                'label' => fake()->words(2, true),
            ]);
        });
    }
}
