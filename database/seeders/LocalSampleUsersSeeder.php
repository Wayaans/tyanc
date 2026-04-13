<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Bootstrap\SyncLocalSampleUsers;
use Illuminate\Database\Seeder;

final class LocalSampleUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        resolve(SyncLocalSampleUsers::class)->handle();
    }
}
