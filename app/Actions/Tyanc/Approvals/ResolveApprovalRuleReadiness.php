<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalRule;

final readonly class ResolveApprovalRuleReadiness
{
    /**
     * @return array{ready: bool, issues: list<string>}
     */
    public function handle(ApprovalRule $approvalRule): array
    {
        $approvalRule->loadMissing('steps.role');

        $issues = [];
        $steps = $approvalRule->steps->sortBy('step_order')->values();

        if ($steps->isEmpty()) {
            $issues[] = __('Add at least one reviewer step.');
        }

        if ($approvalRule->workflow_type === ApprovalRule::WorkflowSingle && $steps->count() > 1) {
            $issues[] = __('Single-step workflows can only define one step.');
        }

        if ($approvalRule->workflow_type === ApprovalRule::WorkflowMulti && $steps->count() === 1) {
            $issues[] = __('Multi-step workflows need at least two reviewer steps.');
        }

        $grantValidityMinutes = is_numeric($approvalRule->grant_validity_minutes)
            ? (int) $approvalRule->grant_validity_minutes
            : null;
        $reminderAfterMinutes = is_numeric($approvalRule->reminder_after_minutes)
            ? (int) $approvalRule->reminder_after_minutes
            : null;
        $escalationAfterMinutes = is_numeric($approvalRule->escalation_after_minutes)
            ? (int) $approvalRule->escalation_after_minutes
            : null;

        if ($grantValidityMinutes === null || $grantValidityMinutes < 1) {
            $issues[] = __('Grant validity must be at least one minute.');
        }

        if ($reminderAfterMinutes !== null && $reminderAfterMinutes < 1) {
            $issues[] = __('Reminder timing must be at least one minute.');
        }

        if ($escalationAfterMinutes !== null && $escalationAfterMinutes < 1) {
            $issues[] = __('Escalation timing must be at least one minute.');
        }

        if (
            $reminderAfterMinutes !== null
            && $escalationAfterMinutes !== null
            && $escalationAfterMinutes <= $reminderAfterMinutes
        ) {
            $issues[] = __('Escalation must happen after the reminder window.');
        }

        return [
            'ready' => $issues === [],
            'issues' => array_values(array_unique($issues)),
        ];
    }
}
