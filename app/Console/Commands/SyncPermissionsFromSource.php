<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Permissions\SyncPermissionsFromSource as SyncPermissionsFromSourceAction;
use Illuminate\Console\Command;

final class SyncPermissionsFromSource extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tyanc:permissions-sync {--json : Output the sync summary as JSON}';

    /**
     * @var string
     */
    protected $description = 'Create missing permission records from config/permission-sot.php';

    public function handle(SyncPermissionsFromSourceAction $action): int
    {
        $result = $action->handle();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Permissions synced from source.');
        $this->table(
            ['Created', 'Existing', 'Total'],
            [[(string) $result['created'], (string) $result['existing'], (string) $result['total']]],
        );

        return self::SUCCESS;
    }
}
