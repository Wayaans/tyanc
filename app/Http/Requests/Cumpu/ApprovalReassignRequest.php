<?php

declare(strict_types=1);

namespace App\Http\Requests\Cumpu;

use App\Models\ApprovalAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ApprovalReassignRequest extends FormRequest
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
            'assignment_id' => ['required', 'string', Rule::exists(ApprovalAssignment::class, 'id')],
            'assigned_to_id' => ['required', 'string', Rule::exists(User::class, 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $assignments = $this->input('assignments');

        if (is_array($assignments) && $assignments !== []) {
            $firstAssignment = collect($assignments)
                ->first(fn (mixed $assignment): bool => is_array($assignment)
                    && is_string($assignment['assignment_id'] ?? null)
                    && is_string($assignment['new_assignee_id'] ?? null));

            if (is_array($firstAssignment)) {
                $this->merge([
                    'assignment_id' => $firstAssignment['assignment_id'],
                    'assigned_to_id' => $firstAssignment['new_assignee_id'],
                ]);
            }
        }
    }
}
