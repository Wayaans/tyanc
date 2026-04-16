<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CreateUserRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'lowercase',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(User::class, 'username'),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'email',
                new ValidEmail,
                Rule::unique(User::class, 'email'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'avatar' => ['prohibited'],
            'status' => ['nullable', Rule::in([UserStatus::Active->value])],
            'locale' => ['nullable', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['nullable', 'timezone'],
            'first_name' => ['prohibited'],
            'last_name' => ['prohibited'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'social_links' => ['nullable', 'array'],
            'social_links.linkedin' => ['nullable', 'url:http,https', 'max:2048'],
            'social_links.twitter' => ['nullable', 'url:http,https', 'max:2048'],
            'social_links.github' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
