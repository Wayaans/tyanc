<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status')->default(UserStatus::Active->value)->after('avatar');
            }

            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->default('UTC')->after('status');
            }

            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 12)->default('en')->after('timezone');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('two_factor_confirmed_at');
            }

            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        $this->backfillUsers();

        if (! Schema::hasIndex('users', ['username'], 'unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        // Forward-fix migration for existing databases.
    }

    private function backfillUsers(): void
    {
        $usedUsernames = DB::table('users')
            ->whereNotNull('username')
            ->pluck('username')
            ->filter(fn (mixed $username): bool => is_string($username) && $username !== '')
            ->map(fn (string $username): string => mb_strtolower($username))
            ->values()
            ->all();

        DB::table('users')
            ->select(['id', 'name', 'email', 'username', 'status', 'timezone', 'locale'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use (&$usedUsernames): void {
                $updates = [];

                if (! is_string($user->username) || $user->username === '') {
                    $updates['username'] = $this->resolveUniqueUsername($user, $usedUsernames);
                }

                if (! is_string($user->status) || $user->status === '') {
                    $updates['status'] = UserStatus::Active->value;
                }

                if (! is_string($user->timezone) || $user->timezone === '') {
                    $updates['timezone'] = 'UTC';
                }

                if (! is_string($user->locale) || $user->locale === '') {
                    $updates['locale'] = 'en';
                }

                if ($updates !== []) {
                    DB::table('users')->where('id', $user->id)->update($updates);
                }
            });
    }

    /**
     * @param  list<string>  $usedUsernames
     */
    private function resolveUniqueUsername(object $user, array &$usedUsernames): string
    {
        $base = null;

        if (isset($user->name) && is_string($user->name) && $user->name !== '') {
            $base = $user->name;
        } elseif (isset($user->email) && is_string($user->email) && $user->email !== '') {
            $base = Str::before($user->email, '@');
        }

        $username = Str::of((string) $base)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-_')
            ->value();

        if ($username === '') {
            $username = 'user';
        }

        $candidate = $username;
        $suffix = 2;

        while (in_array(mb_strtolower($candidate), $usedUsernames, true)) {
            $candidate = sprintf('%s-%d', $username, $suffix);
            $suffix++;
        }

        $usedUsernames[] = mb_strtolower($candidate);

        return $candidate;
    }
};
