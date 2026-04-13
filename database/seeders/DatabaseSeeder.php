<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        throw_unless(app()->environment(['local', 'testing']), RuntimeException::class, 'DatabaseSeeder is only for local and testing environments. Use "php artisan tyanc:bootstrap --no-interaction" in non-local environments.');

        $this->call(LocalDevelopmentSeeder::class);
    }
}
