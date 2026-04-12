<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class ExpireApprovalGrants
{
    public function handle(?CarbonInterface $referenceTime = null): int
    {
        $resolvedReferenceTime = $referenceTime?->copy() ?? now();

        return DB::transaction(function () use ($resolvedReferenceTime): int {
            $expiredRequests = ApprovalRequest::query()
                ->with(['subject'])
                ->whereIn('status', ApprovalRequest::consumableStatuses())
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $resolvedReferenceTime)
                ->lockForUpdate()
                ->get();

            $expiredRequests->each(function (ApprovalRequest $approvalRequest) use ($resolvedReferenceTime): void {
                $approvalRequest->forceFill([
                    'status' => ApprovalRequest::StatusExpired,
                    'updated_at' => $resolvedReferenceTime,
                ])->save();

                activity('approvals')
                    ->performedOn($approvalRequest->subject ?? $approvalRequest)
                    ->event('expired')
                    ->withProperties([
                        'approval_request_id' => (string) $approvalRequest->id,
                        'expires_at' => $approvalRequest->expires_at?->toIso8601String(),
                    ])
                    ->log('Approval grant expired');
            });

            return $expiredRequests->count();
        });
    }
}
