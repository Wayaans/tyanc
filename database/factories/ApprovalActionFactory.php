<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Tyanc\Approvals\ApplyUsersImportApproval;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalAction>
 */
final class ApprovalActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_request_id' => ApprovalRequest::factory(),
            'handler' => ApplyUsersImportApproval::class,
            'payload' => [
                'original_name' => 'users.xlsx',
                'staged_file_path' => 'approvals/imports/users.xlsx',
            ],
        ];
    }
}
