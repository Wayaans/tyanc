<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Data\Tyanc\Activity\ActivityLogEntryData;
use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

final readonly class ListApprovalRequestHistory
{
    /**
     * @return array<int, ActivityLogEntryData>
     */
    public function handle(ApprovalRequest $approvalRequest): array
    {
        return Activity::query()
            ->with(['subject', 'causer'])
            ->where('log_name', 'approvals')
            ->where(function (Builder $query) use ($approvalRequest): void {
                $query
                    ->where(function (Builder $requestQuery) use ($approvalRequest): void {
                        $requestQuery
                            ->where('subject_type', $approvalRequest::class)
                            ->where('subject_id', (string) $approvalRequest->getKey());
                    })
                    ->orWhere('properties->approval_request_id', (string) $approvalRequest->getKey());
            })
            ->oldest('created_at')
            ->get()
            ->map(fn (Activity $activity): ActivityLogEntryData => ActivityLogEntryData::fromModel($activity))
            ->all();
    }
}
