<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Arr;

final readonly class UpsertUserProfile
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): UserProfile
    {
        $profileAttributes = Arr::only($attributes, [
            'first_name',
            'last_name',
            'phone_number',
            'date_of_birth',
            'gender',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'country',
            'postal_code',
            'company_name',
            'job_title',
            'bio',
            'social_links',
        ]);

        if (($profileAttributes['first_name'] ?? null) === null && ($profileAttributes['last_name'] ?? null) === null) {
            [$firstName, $lastName] = $this->splitName($attributes['name'] ?? null);

            $profileAttributes['first_name'] = $firstName;
            $profileAttributes['last_name'] = $lastName;
        }

        /** @var UserProfile $profile */
        $profile = $user->profile()->updateOrCreate([], $profileAttributes);

        return $profile;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitName(mixed $name): array
    {
        if (! is_string($name)) {
            return [null, null];
        }

        $segments = preg_split('/\s+/', mb_trim($name));

        if ($segments === false || $segments === [] || $segments[0] === '') {
            return [null, null];
        }

        $firstName = $segments[0];
        $lastName = count($segments) > 1 ? implode(' ', array_slice($segments, 1)) : null;

        return [$firstName, $lastName];
    }
}
