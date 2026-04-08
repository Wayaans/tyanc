<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('user_profiles')) {
            return;
        }

        $hasLegacyNameColumn = Schema::hasColumn('users', 'name');

        DB::table('users')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->whereNull('user_profiles.user_id')
            ->select([
                'users.id',
                'users.username',
                'users.created_at',
                'users.updated_at',
                ...($hasLegacyNameColumn ? ['users.name'] : []),
            ])
            ->orderBy('users.id')
            ->get()
            ->each(function (object $user) use ($hasLegacyNameColumn): void {
                [$firstName, $lastName] = $this->splitName(
                    $hasLegacyNameColumn && isset($user->name) && is_string($user->name)
                        ? $user->name
                        : (is_string($user->username) ? $user->username : null),
                );

                DB::table('user_profiles')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'created_at' => $user->created_at ?? now(),
                    'updated_at' => $user->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        // Forward-fix migration for existing databases.
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitName(?string $name): array
    {
        if ($name === null || mb_trim($name) === '') {
            return [null, null];
        }

        $segments = preg_split('/\s+/', mb_trim($name));

        if ($segments === false || $segments === []) {
            return [null, null];
        }

        $firstName = $segments[0] ?: null;
        $lastName = count($segments) > 1 ? implode(' ', array_slice($segments, 1)) : null;

        return [$firstName, $lastName];
    }
};
