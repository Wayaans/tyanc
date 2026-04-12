<?php

declare(strict_types=1);

namespace App\Http\Requests\Cumpu;

use App\Models\ApprovalRule;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateApprovalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'resource_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'action_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'enabled' => ['sometimes', 'boolean'],
            'workflow_type' => ['required', 'string', Rule::in([ApprovalRule::WorkflowSingle, ApprovalRule::WorkflowMulti])],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'steps.*.label' => ['nullable', 'string', 'max:120'],
            'grant_validity_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'reminder_after_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'escalation_after_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $steps = $this->input('steps', []);
                $workflowType = (string) $this->input('workflow_type', ApprovalRule::WorkflowSingle);
                $reminderAfterMinutes = $this->integer('reminder_after_minutes');
                $escalationAfterMinutes = $this->integer('escalation_after_minutes');

                if ($workflowType === ApprovalRule::WorkflowMulti && count($steps) < 2) {
                    $validator->errors()->add('steps', __('Multi-step workflows must define at least two steps.'));
                }

                if (
                    $reminderAfterMinutes > 0
                    && $escalationAfterMinutes > 0
                    && $escalationAfterMinutes <= $reminderAfterMinutes
                ) {
                    $validator->errors()->add('escalation_after_minutes', __('Escalation must happen after the reminder window.'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $steps = $this->input('steps');

        if (! is_array($steps) || $steps === []) {
            $legacyRoleId = $this->input('role_id', $this->input('step_role_id'));

            if (is_numeric($legacyRoleId)) {
                $steps = [[
                    'role_id' => (int) $legacyRoleId,
                    'label' => $this->input('step_label'),
                ]];
            }
        }

        if (! is_array($steps)) {
            $steps = [];
        }

        $normalizedSteps = collect($steps)
            ->filter(fn (mixed $step): bool => is_array($step))
            ->values()
            ->map(fn (array $step): array => [
                'role_id' => isset($step['role_id']) && is_numeric($step['role_id']) ? (int) $step['role_id'] : null,
                'label' => is_string($step['label'] ?? null) ? $step['label'] : null,
            ])
            ->all();

        $workflowType = $this->input('workflow_type');

        if (! is_string($workflowType) || $workflowType === '') {
            $workflowType = count($normalizedSteps) > 1
                ? ApprovalRule::WorkflowMulti
                : ApprovalRule::WorkflowSingle;
        }

        $this->merge([
            'enabled' => $this->boolean('enabled', false),
            'workflow_type' => $workflowType,
            'steps' => $normalizedSteps,
            'grant_validity_minutes' => $this->filled('grant_validity_minutes') ? $this->integer('grant_validity_minutes') : null,
            'reminder_after_minutes' => $this->filled('reminder_after_minutes') ? $this->integer('reminder_after_minutes') : null,
            'escalation_after_minutes' => $this->filled('escalation_after_minutes') ? $this->integer('escalation_after_minutes') : null,
        ]);
    }
}
