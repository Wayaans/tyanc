<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRuleStep>
 */
final class ApprovalRuleStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_rule_id' => ApprovalRule::factory(),
            'role_id' => $this->roleId(),
            'step_order' => 1,
            'label' => fake()->words(2, true),
        ];
    }

    private function roleId(): int
    {
        /** @var Role|null $role */
        $role = Role::query()->first();

        if ($role instanceof Role) {
            return (int) $role->id;
        }

        return (int) Role::query()->create([
            'name' => sprintf('Approver %s', fake()->unique()->word()),
            'guard_name' => 'web',
            'level' => fake()->numberBetween(10, 100),
        ])->id;
    }
}
