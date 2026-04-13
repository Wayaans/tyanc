<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Bootstrap\SyncReservedRolePermissions;
use Illuminate\Database\Seeder;

final class AccessMatrixSeeder extends Seeder
{
    public function run(): void
    {
        resolve(SyncReservedRolePermissions::class)->handle();
    }
}
