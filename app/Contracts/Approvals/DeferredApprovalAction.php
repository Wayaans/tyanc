<?php

declare(strict_types=1);

namespace App\Contracts\Approvals;

use App\Models\ApprovalRequest;

interface DeferredApprovalAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(ApprovalRequest $approvalRequest, array $payload = []): mixed;
}
