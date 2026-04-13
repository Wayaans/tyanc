<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Bootstrap\SyncConfiguredApps;
use Illuminate\Database\Seeder;

final class AppRegistrySeeder extends Seeder
{
    public function run(): void
    {
        resolve(SyncConfiguredApps::class)->handle();
    }
}
