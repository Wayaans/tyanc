<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Bootstrap\SyncReservedRoles;
use App\Actions\Tyanc\Permissions\SyncPermissionsFromSource;
use Illuminate\Database\Seeder;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        resolve(SyncPermissionsFromSource::class)->handle();
        resolve(SyncReservedRoles::class)->handle();
    }
}
