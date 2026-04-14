<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Approvals\SyncApprovalRulesFromSource as SyncApprovalRulesFromSourceAction;
use Illuminate\Console\Command;
use JsonException;

final class SyncApprovalRulesFromSource extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tyanc:approval-rules-sync {--json : Output the sync summary as JSON}';

    /**
     * @var string
     */
    protected $description = 'Sync config-managed approval rules from config/approval-sot.php';

    public function handle(SyncApprovalRulesFromSourceAction $action): int
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

        $this->components->info('Approval rules synced from source.');
        $this->table(
            ['Created', 'Updated', 'Converted', 'Retired', 'Checked', 'Total'],
            [[
                (string) $result['created'],
                (string) $result['updated'],
                (string) $result['converted'],
                (string) $result['retired'],
                (string) $result['checked'],
                (string) $result['total'],
            ]],
        );

        return self::SUCCESS;
    }
}
