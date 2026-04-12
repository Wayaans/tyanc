<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
final class UserProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone_number' => fake()->e164PhoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['female', 'male', 'non_binary', 'prefer_not_to_say']),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->buildingNumber(),
            'city' => fake()->city(),
            'state' => fake()->word(),
            'country' => fake()->countryCode(),
            'postal_code' => fake()->postcode(),
            'company_name' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'social_links' => [
                'linkedin' => 'https://linkedin.com/in/'.fake()->userName(),
                'twitter' => 'https://x.com/'.fake()->userName(),
                'github' => 'https://github.com/'.fake()->userName(),
            ],
        ];
    }
}
