<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TyancBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedBootstrapUsers();
        });
    }

    private function seedBootstrapUsers(): void
    {
        $faker = FakerFactory::create('id_ID');
        $faker->seed(20260411);

        for ($index = 1; $index <= 3; $index++) {
            $this->seedIndonesianUser($faker, $index);
        }
    }

    private function seedIndonesianUser(Generator $faker, int $index): void
    {
        $name = (string) $faker->name();
        $username = str($name)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-_')
            ->append('-'.$index)
            ->value();
        $email = sprintf('%s@bootstrap.tyanc.test', $username);

        $user = User::query()->withTrashed()->firstOrNew([
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $this->generatedPassword($email),
            'avatar' => null,
            'status' => UserStatus::Active,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'is_reserved' => false,
            'reserved_key' => null,
            'email_verified_at' => now()->subDays($index),
        ]);
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->preference()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'locale' => 'id',
                'timezone' => 'Asia/Jakarta',
                'appearance' => 'system',
                'sidebar_variant' => 'inset',
                'spacing_density' => 'default',
            ],
        );

        $user->syncRoles([]);
        $user->syncPermissions([]);
    }

    private function generatedPassword(string $email): string
    {
        $appKey = (string) config('app.key', 'tyanc-bootstrap');

        return hash('sha256', sprintf('%s|%s|bootstrap-users', $appKey, $email));
    }
}
