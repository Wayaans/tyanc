<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use Illuminate\Foundation\Http\FormRequest;

final class ApprovalDecisionRequest extends FormRequest
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
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
