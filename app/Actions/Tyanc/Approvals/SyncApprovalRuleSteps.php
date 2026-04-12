<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalRule;

final readonly class SyncApprovalRuleSteps
{
    /**
     * @param  list<array{role_id: int, label?: string|null}>  $steps
     */
    public function handle(ApprovalRule $approvalRule, array $steps): ApprovalRule
    {
        $normalizedSteps = collect($steps)
            ->values()
            ->map(fn (array $step, int $index): array => [
                'step_order' => $index + 1,
                'role_id' => $step['role_id'],
                'label' => $this->nullableString($step['label'] ?? null),
            ]);

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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
