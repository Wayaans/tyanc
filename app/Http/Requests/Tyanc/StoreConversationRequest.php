<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('messages', 'create'));
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(User::class, 'id'),
                Rule::notIn([(string) $this->user()?->id]),
            ],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => is_string($this->input('subject')) ? mb_trim($this->string('subject')->toString()) : $this->input('subject'),
            'message' => is_string($this->input('message')) ? mb_trim($this->string('message')->toString()) : $this->input('message'),
        ]);
    }
}
