<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DevelopmentAccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AppRegistrySeeder::class,
            PermissionsSyncSeeder::class,
        ]);

        DB::transaction(function (): void {
            $superAdminRole = Role::query()->updateOrCreate(
                [
                    'name' => (string) config('tyanc.reserved_roles.super_admin'),
                    'guard_name' => 'web',
                ],
                [
                    'level' => 100,
                ],
            );

            Role::query()->updateOrCreate(
                [
                    'name' => (string) config('tyanc.reserved_roles.admin'),
                    'guard_name' => 'web',
                ],
                [
                    'level' => 90,
                ],
            );

            $user = User::query()->withTrashed()->firstOrNew([
                'email' => 'supa@app.com',
            ]);

            $user->forceFill([
                'username' => 'supa-manuse',
                'password' => 'password',
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Makassar',
                'locale' => 'en',
                'email_verified_at' => now(),
                'avatar' => null,
                ...$this->legacyNameAttributes(),
            ]);
            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => 'Supa',
                    'last_name' => 'Manuse',
                    'phone_number' => '+6281234567890',
                    'date_of_birth' => '1990-01-15',
                    'gender' => 'prefer_not_to_say',
                    'address_line_1' => 'Jalan Teuku Umar No. 88',
                    'address_line_2' => 'Suite 12',
                    'city' => 'Denpasar',
                    'state' => 'Bali',
                    'country' => 'ID',
                    'postal_code' => '80114',
                    'company_name' => 'Tyanc',
                    'job_title' => 'Platform Owner',
                    'bio' => 'Development super administrator account for the Tyanc workspace.',
                    'social_links' => [
                        'linkedin' => 'https://linkedin.com/in/supa-manuse',
                        'twitter' => 'https://x.com/supa_manuse',
                        'github' => 'https://github.com/supa-manuse',
                    ],
                ],
            );

            $user->syncRoles([$superAdminRole]);
            $user->preference()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'locale' => 'en',
                    'timezone' => 'Asia/Makassar',
                    'appearance' => 'dark',
                    'sidebar_variant' => 'inset',
                    'spacing_density' => 'default',
                ],
            );
        });
    }

    /**
     * @return array<string, string>
     */
    private function legacyNameAttributes(): array
    {
        if (! Schema::hasColumn('users', 'name')) {
            return [];
        }

        return [
            'name' => 'Supa Manuse',
        ];
    }
}
