<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Bootstrap\ResolveBootstrapStatus;
use Illuminate\Console\Command;
use JsonException;

final class CheckTyancBootstrap extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tyanc:bootstrap-check {--json : Output the bootstrap status as JSON}';

    /**
     * @var string
     */
    protected $description = 'Check whether Tyanc bootstrap metadata is complete';

    public function handle(ResolveBootstrapStatus $action): int
    {
        $status = $action->handle();

        if ($this->option('json')) {
            try {
                $this->line(json_encode($status, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            } catch (JsonException $jsonException) {
                $this->components->error($jsonException->getMessage());

                return self::FAILURE;
            }

            return $status['ready'] ? self::SUCCESS : self::FAILURE;
        }

        if ($status['ready']) {
            $this->components->info('Tyanc bootstrap is ready.');

            if ($status['warnings'] !== []) {
                $this->newLine();
                $this->components->warn('Warnings:');

                foreach ($status['warnings'] as $warning) {
                    $this->line(sprintf('- %s', $warning));
                }
            }

            return self::SUCCESS;
        }

        $this->components->error('Tyanc bootstrap is incomplete.');
        $this->line('Run: php artisan tyanc:bootstrap --no-interaction');
        $this->newLine();

        foreach ($status['missing'] as $missing) {
            $this->line(sprintf('- %s', $missing));
        }

        if ($status['warnings'] !== []) {
            $this->newLine();
            $this->components->warn('Warnings:');

            foreach ($status['warnings'] as $warning) {
                $this->line(sprintf('- %s', $warning));
            }
        }

        return self::FAILURE;
    }
}
