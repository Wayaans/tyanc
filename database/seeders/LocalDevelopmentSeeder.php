<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call([
            AppRegistrySeeder::class,
            RolesAndPermissionsSeeder::class,
            AccessMatrixSeeder::class,
            LocalReservedUsersSeeder::class,
            LocalSampleUsersSeeder::class,
        ]);
    }
}
