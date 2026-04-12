<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tyanc\Approvals\FindOverdueApprovals;
use App\Jobs\SendApprovalEscalation;
use App\Jobs\SendApprovalReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\PendingDispatch;

#[Signature('approvals:dispatch-escalations')]
#[Description('Dispatch approval reminder and escalation jobs for overdue requests')]
final class DispatchApprovalEscalations extends Command
{
    public function handle(FindOverdueApprovals $overdueApprovals): int
    {
        $reminders = $overdueApprovals->handle('reminder');
        $escalations = $overdueApprovals->handle('escalation');

        $reminders->each(fn ($approvalRequest): PendingDispatch => dispatch(new SendApprovalReminder((string) $approvalRequest->id)));
        $escalations->each(fn ($approvalRequest): PendingDispatch => dispatch(new SendApprovalEscalation((string) $approvalRequest->id)));

        $this->info(__('Queued :reminders reminders and :escalations escalations.', [
            'reminders' => $reminders->count(),
            'escalations' => $escalations->count(),
        ]));

        return self::SUCCESS;
    }
}
