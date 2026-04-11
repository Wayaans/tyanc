<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'name')) {
                $table->string('name')->default('')->after('id');
            }

            if (! Schema::hasColumn('users', 'is_reserved')) {
                $table->boolean('is_reserved')->default(false)->after('locale')->index();
            }

            if (! Schema::hasColumn('users', 'reserved_key')) {
                $table->string('reserved_key')->nullable()->unique()->after('is_reserved');
            }
        });

        $this->backfillDisplayNames();
        $this->backfillReservedUsers();
    }

    public function down(): void
    {
        // Forward-fix migration for production upgrades.
    }

    private function backfillDisplayNames(): void
    {
        $hasProfiles = Schema::hasTable('user_profiles');

        $query = DB::table('users')
            ->select(['users.id', 'users.name', 'users.username']);

        if ($hasProfiles) {
            $query
                ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
                ->addSelect(['user_profiles.first_name', 'user_profiles.last_name']);
        }

        $query
            ->orderBy('users.id')
            ->get()
            ->each(function (object $user) use ($hasProfiles): void {
                $name = is_string($user->name ?? null) ? mb_trim($user->name) : '';

                if ($name === '' && $hasProfiles) {
                    $segments = array_values(array_filter([
                        is_string($user->first_name ?? null) ? mb_trim($user->first_name) : null,
                        is_string($user->last_name ?? null) ? mb_trim($user->last_name) : null,
                    ]));

                    $name = implode(' ', $segments);
                }

                if ($name === '') {
                    $name = is_string($user->username ?? null) && $user->username !== ''
                        ? $user->username
                        : 'User';
                }

                DB::table('users')->where('id', $user->id)->update([
                    'name' => $name,
                ]);
            });
    }

    private function backfillReservedUsers(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $reservedRoleMap = [
            'super_admin' => (string) config('tyanc.reserved_roles.super_admin'),
            'admin' => (string) config('tyanc.reserved_roles.admin'),
        ];

        foreach ($reservedRoleMap as $reservedKey => $roleName) {
            if ($roleName === '') {
                continue;
            }

            $userId = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', User::class)
                ->where('roles.name', $roleName)
                ->orderBy('model_has_roles.model_id')
                ->value('model_has_roles.model_id');
            if (! is_string($userId)) {
                continue;
            }

            if ($userId === '') {
                continue;
            }

            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'is_reserved' => true,
                    'reserved_key' => $reservedKey,
                ]);
        }
    }
};
