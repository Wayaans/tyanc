<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Bootstrap\RunProductionBootstrap;
use App\Actions\Tyanc\Users\EnsureReservedUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class CreateReservedSuperAdmin extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tyanc:create-super-admin
        {--name= : Full name for the reserved super admin user}
        {--username= : Username for the reserved super admin user}
        {--email= : Email address for the reserved super admin user}
        {--password= : Password for the reserved super admin user}
        {--locale= : Locale for the reserved super admin user}
        {--timezone= : Timezone for the reserved super admin user}';

    /**
     * @var string
     */
    protected $description = 'Create the reserved Supa Manuse super admin user when one does not already exist';

    public function handle(EnsureReservedUser $action, RunProductionBootstrap $bootstrap): int
    {
        if (User::query()->withTrashed()->where('reserved_key', 'super_admin')->exists()) {
            $this->components->error('A reserved super admin user already exists. Aborting to prevent multiple super admins.');

            return self::FAILURE;
        }

        $attributes = [
            'name' => $this->optionValue('name') ?? $this->askWithDefault('Full name', (string) config('tyanc.reserved_users.super_admin.name', 'Supa Manuse')),
            'username' => $this->optionValue('username') ?? $this->askWithDefault('Username', (string) config('tyanc.reserved_users.super_admin.username', 'supa-manuse')),
            'email' => $this->optionValue('email') ?? $this->askWithDefault('Email address', (string) config('tyanc.reserved_users.super_admin.email', 'supa@app.com')),
            'password' => $this->optionValue('password') ?? $this->secret('Password'),
            'locale' => $this->optionValue('locale') ?? $this->askWithDefault('Locale', (string) config('tyanc.reserved_users.super_admin.locale', 'en')),
            'timezone' => $this->optionValue('timezone') ?? $this->askWithDefault('Timezone', (string) config('tyanc.reserved_users.super_admin.timezone', 'Asia/Makassar')),
        ];

        if (! is_string($attributes['password']) || mb_trim($attributes['password']) === '') {
            $this->components->error('A password is required.');

            return self::FAILURE;
        }

        $bootstrapResult = $bootstrap->handle();

        if (! $bootstrapResult['status']['ready']) {
            $this->components->error('Tyanc bootstrap is incomplete. Resolve the missing metadata before creating the reserved super admin.');

            foreach ($bootstrapResult['status']['missing'] as $missing) {
                $this->line(sprintf('- %s', $missing));
            }

            return self::FAILURE;
        }

        try {
            $user = $action->handle('super_admin', $attributes);
        } catch (ValidationException $validationException) {
            foreach ($validationException->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->components->info('Reserved super admin user created successfully.');
        $this->table(
            ['Reserved Key', 'Name', 'Username', 'Email'],
            [[
                (string) $user->reserved_key,
                $user->name,
                $user->username,
                $user->email,
            ]],
        );

        return self::SUCCESS;
    }

    private function askWithDefault(string $label, string $default): string
    {
        $value = $this->ask($label, $default);

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : $default;
    }

    private function optionValue(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }
}
