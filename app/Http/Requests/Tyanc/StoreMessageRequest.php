<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $conversation = $this->route('conversation');

        if ($user === null || ! $conversation instanceof Conversation) {
            return false;
        }

        return resolve(PermissionResourceAccess::class)->handle($user, 'tyanc.messages.create')
            && $conversation->participants()->whereKey($user->getKey())->exists();
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
