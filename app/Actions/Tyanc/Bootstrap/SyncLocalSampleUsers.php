<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Bootstrap;

use App\Enums\UserStatus;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SyncLocalSampleUsers
{
    /**
     * @return array{total: int, users: list<string>}
     */
    public function handle(): array
    {
        throw_unless(app()->environment(['local', 'testing']), RuntimeException::class, 'The local sample-user bootstrap is only available in local and testing environments.');

        $users = [];

        DB::transaction(function () use (&$users): void {
            $faker = FakerFactory::create('id_ID');
            $faker->seed(20260411);

            for ($index = 1; $index <= 3; $index++) {
                $users[] = $this->syncIndonesianUser($faker, $index);
            }
        });

        return [
            'total' => count($users),
            'users' => $users,
        ];
    }

    private function syncIndonesianUser(Generator $faker, int $index): string
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

        return $email;
    }

    private function generatedPassword(string $email): string
    {
        $appKey = (string) config('app.key', 'tyanc-bootstrap');

        return hash('sha256', sprintf('%s|%s|bootstrap-users', $appKey, $email));
    }
}
