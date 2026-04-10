<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Permissions\SyncPermissionsFromSource;
use Illuminate\Database\Seeder;

final class PermissionsSyncSeeder extends Seeder
{
    public function run(): void
    {
        resolve(SyncPermissionsFromSource::class)->handle();
    }
}
