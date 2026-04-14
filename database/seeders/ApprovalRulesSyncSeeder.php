<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Approvals\SyncApprovalRulesFromSource;
use Illuminate\Database\Seeder;

final class ApprovalRulesSyncSeeder extends Seeder
{
    public function run(): void
    {
        resolve(SyncApprovalRulesFromSource::class)->handle();
    }
}
