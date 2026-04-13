<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Bootstrap\RunProductionBootstrap;
use Illuminate\Console\Command;
use JsonException;

final class BootstrapTyanc extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tyanc:bootstrap {--json : Output the bootstrap summary as JSON}';

    /**
     * @var string
     */
    protected $description = 'Bootstrap Tyanc production-safe metadata and reserved RBAC state';

    public function handle(RunProductionBootstrap $action): int
    {
        $result = $action->handle();

        if ($this->option('json')) {
            try {
                $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            } catch (JsonException $jsonException) {
                $this->components->error($jsonException->getMessage());

                return self::FAILURE;
            }

            return $result['status']['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info('Tyanc production bootstrap completed.');
        $this->table(
            ['Permissions', 'Apps', 'Roles', 'Role permissions'],
            [[
                (string) $result['permissions']['total'],
                (string) count($result['apps']['apps']),
                (string) count($result['roles']['roles']),
                (string) $result['role_permissions']['total'],
            ]],
        );

        if ($result['status']['warnings'] !== []) {
            $this->newLine();
            $this->components->warn('Warnings:');

            foreach ($result['status']['warnings'] as $warning) {
                $this->line(sprintf('- %s', $warning));
            }
        }

        if ($result['status']['ready']) {
            $this->components->info('Tyanc bootstrap is ready.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->error('Tyanc bootstrap is still incomplete.');

        foreach ($result['status']['missing'] as $missing) {
            $this->line(sprintf('- %s', $missing));
        }

        return self::FAILURE;
    }
}
