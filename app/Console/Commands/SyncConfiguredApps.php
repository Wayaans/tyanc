<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Bootstrap\SyncConfiguredApps as SyncConfiguredAppsAction;
use Illuminate\Console\Command;
use JsonException;

final class SyncConfiguredApps extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tyanc:apps-sync {--json : Output the sync summary as JSON}';

    /**
     * @var string
     */
    protected $description = 'Sync configured apps and managed app pages from config/sidebar-menu.php';

    public function handle(SyncConfiguredAppsAction $action): int
    {
        $result = $action->handle();

        if ($this->option('json')) {
            try {
                $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            } catch (JsonException $jsonException) {
                $this->components->error($jsonException->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->components->info('Configured apps synced.');
        $this->table(
            ['Created', 'Existing', 'Pages synced', 'Skipped'],
            [[
                (string) $result['created'],
                (string) $result['existing'],
                (string) $result['synced'],
                (string) $result['skipped'],
            ]],
        );

        return self::SUCCESS;
    }
}
