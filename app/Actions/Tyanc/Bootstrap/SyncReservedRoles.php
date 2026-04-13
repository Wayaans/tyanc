<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Bootstrap;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

final readonly class SyncReservedRoles
{
    /**
     * @return array{created: int, existing: int, roles: list<string>}
     */
    public function handle(): array
    {
        $created = 0;
        $existing = 0;
        $roles = [];

        DB::transaction(function () use (&$created, &$existing, &$roles): void {
            foreach ($this->roles() as $name => $level) {
                $role = Role::query()->updateOrCreate(
                    [
                        'name' => $name,
                        'guard_name' => 'web',
                    ],
                    [
                        'level' => $level,
                    ],
                );

                $roles[] = $role->name;

                if ($role->wasRecentlyCreated) {
                    $created++;

                    continue;
                }

                $existing++;
            }
        });

        return [
            'created' => $created,
            'existing' => $existing,
            'roles' => $roles,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function roles(): array
    {
        return [
            (string) config('tyanc.reserved_roles.super_admin') => 100,
            (string) config('tyanc.reserved_roles.admin') => 90,
        ];
    }
}
