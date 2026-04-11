<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Users\EnsureReservedUser;
use Illuminate\Database\Seeder;

final class LocalReservedUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $reservedUsers = resolve(EnsureReservedUser::class);

        $reservedUsers->handle('super_admin', [
            'password' => 'password',
        ]);

        $reservedUsers->handle('admin', [
            'password' => 'password',
        ]);
    }
}
