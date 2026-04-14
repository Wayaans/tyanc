<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalRule;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

final readonly class SyncApprovalRuleSteps
{
    /**
     * @param  list<array{role_id?: int|null, role_name?: string|null, label?: string|null}>  $steps
     */
    public function handle(ApprovalRule $approvalRule, array $steps): ApprovalRule
    {
        $normalizedSteps = collect($steps)
            ->values()
            ->map(function (array $step, int $index): array {
                $roleId = $this->resolveRoleId($step['role_id'] ?? null, $step['role_name'] ?? null);

                if ($roleId === null) {
                    throw ValidationException::withMessages([
                        'steps' => __('Reviewer role for step :step is not available.', [
                            'step' => $index + 1,
                        ]),
                    ]);
                }

                return [
                    'step_order' => $index + 1,
                    'role_id' => $roleId,
                    'label' => $this->nullableString($step['label'] ?? null),
                ];
            });

        $normalizedSteps->each(function (array $step) use ($approvalRule): void {
            $approvalRule->steps()->updateOrCreate(
                ['step_order' => $step['step_order']],
                [
                    'role_id' => $step['role_id'],
                    'label' => $step['label'],
                ],
            );
        });

        $approvalRule->steps()
            ->whereNotIn('step_order', $normalizedSteps->pluck('step_order')->all())
            ->delete();

        return $approvalRule->fresh('steps.role');
    }

    private function resolveRoleId(mixed $roleId, mixed $roleName): ?int
    {
        if (is_numeric($roleId)) {
            return (int) $roleId;
        }

        if (! is_string($roleName) || mb_trim($roleName) === '') {
            return null;
        }

        $role = Role::query()
            ->where('name', mb_trim($roleName))
            ->first();

        return $role instanceof Role
            ? (int) $role->id
            : null;
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
