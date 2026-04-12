<?php

declare(strict_types=1);

namespace App\Data\Cumpu\Approvals;

use Spatie\LaravelData\Data;

final class ApprovalContextData extends Data
{
    /**
     * @param  array<int, ApprovalContextRequestData>  $history
     * @param  array<string, GovernedActionStateData>  $governed_actions
     */
    public function __construct(
        public string $scope_label,
        public int $pending_count,
        public bool $has_pending_requests,
        public bool $can_view_requests,
        public ?ApprovalContextRequestData $latest_pending_request,
        public array $history,
        public array $governed_actions = [],
    ) {}
}
