<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Bootstrap\RunLocalDevelopmentBootstrap;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

final class BootstrapTyancLocal extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tyanc:bootstrap-local {--json : Output the local bootstrap summary as JSON}';

    /**
     * @var string
     */
    protected $description = 'Bootstrap Tyanc local and testing metadata, reserved users, and sample users';

    public function handle(RunLocalDevelopmentBootstrap $action): int
    {
        try {
            $result = $action->handle();
        } catch (RuntimeException $runtimeException) {
            $this->components->error($runtimeException->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            try {
                $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            } catch (JsonException $jsonException) {
                $this->components->error($jsonException->getMessage());

                return self::FAILURE;
            }

            return $result['production']['status']['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info('Tyanc local bootstrap completed.');
        $this->table(
            ['Reserved users', 'Sample users'],
            [[
                (string) count($result['reserved_users']),
                (string) $result['sample_users']['total'],
            ]],
        );

        if (! $result['production']['status']['ready']) {
            $this->components->error('Tyanc local bootstrap is still incomplete.');

            foreach ($result['production']['status']['missing'] as $missing) {
                $this->line(sprintf('- %s', $missing));
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
