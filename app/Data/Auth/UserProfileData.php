<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Models\UserProfile;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class UserProfileData extends Data
{
    /**
     * @param  array<string, string>|null  $social_links
     */
    public function __construct(
        public string $id,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $phone_number,
        public ?string $date_of_birth,
        public ?string $gender,
        public ?string $address_line_1,
        public ?string $address_line_2,
        public ?string $city,
        public ?string $state,
        public ?string $country,
        public ?string $postal_code,
        public ?string $company_name,
        public ?string $job_title,
        public ?string $bio,
        public ?array $social_links,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(UserProfile $profile): self
    {
        return new self(
            id: (string) $profile->id,
            first_name: $profile->first_name,
            last_name: $profile->last_name,
            phone_number: $profile->phone_number,
            date_of_birth: $profile->date_of_birth?->toDateString(),
            gender: $profile->gender,
            address_line_1: $profile->address_line_1,
            address_line_2: $profile->address_line_2,
            city: $profile->city,
            state: $profile->state,
            country: $profile->country,
            postal_code: $profile->postal_code,
            company_name: $profile->company_name,
            job_title: $profile->job_title,
            bio: $profile->bio,
            social_links: $profile->social_links,
            created_at: $profile->created_at instanceof CarbonInterface ? $profile->created_at->toIso8601String() : now()->toIso8601String(),
            updated_at: $profile->updated_at instanceof CarbonInterface ? $profile->updated_at->toIso8601String() : now()->toIso8601String(),
        );
    }
}
