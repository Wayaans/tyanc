<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Contracts\Approvals\DeferredApprovalAction;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use RuntimeException;

final readonly class ExecuteApprovedAction
{
    public function handle(ApprovalRequest $approvalRequest): mixed
    {
        $approvalRequest->loadMissing('actionRecord');

        $actionRecord = $approvalRequest->actionRecord;

        if (! $actionRecord instanceof ApprovalAction) {
            return null;
        }

        $handler = $actionRecord->handler;

        if (! is_string($handler) || $handler === '' || ! class_exists($handler)) {
            throw new RuntimeException(__('The approval handler is invalid.'));
        }

        $resolvedHandler = resolve($handler);

        if (! $resolvedHandler instanceof DeferredApprovalAction) {
            throw new RuntimeException(__('The approval handler is not executable.'));
        }

        return $resolvedHandler->handle(
            $approvalRequest,
            is_array($actionRecord->payload) ? $actionRecord->payload : [],
        );
    }
}
