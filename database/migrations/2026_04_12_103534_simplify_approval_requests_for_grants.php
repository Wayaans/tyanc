<?php

declare(strict_types=1);

use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_requests')) {
            Schema::table('approval_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('approval_requests', 'consumed_by_id')) {
                    $table->foreignUuid('consumed_by_id')
                        ->nullable()
                        ->after('cancelled_by_id')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('approval_requests', 'consumed_at')) {
                    $table->timestamp('consumed_at')
                        ->nullable()
                        ->after('expires_at')
                        ->index();
                }

                $table->index(
                    ['requested_by_id', 'action', 'subject_type', 'subject_id', 'status', 'expires_at'],
                    'approval_requests_grant_lookup_index',
                );
            });
        }

        if (Schema::hasTable('approval_rules')) {
            Schema::table('approval_rules', function (Blueprint $table): void {
                if (! Schema::hasColumn('approval_rules', 'grant_validity_minutes')) {
                    $table->unsignedInteger('grant_validity_minutes')
                        ->default(1440)
                        ->after('conditions');
                }
            });
        }

        $this->expireReplayEraRequests();
    }

    public function down(): void
    {
        // Forward-fix migration for production upgrades.
    }

    private function expireReplayEraRequests(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        $openStatuses = [
            ApprovalRequest::StatusPending,
            ApprovalRequest::StatusInReview,
            ApprovalRequest::StatusApproved,
        ];

        /** @var Collection<int, string> $requestIds */
        $requestIds = DB::table('approval_requests')
            ->whereIn('status', $openStatuses)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values();

        if ($requestIds->isEmpty()) {
            return;
        }

        $now = now();
        $resubmissionMessage = 'Expired during the approval grant rollout. Please resubmit this request using the new approval-grant flow.';

        DB::table('approval_requests')
            ->whereIn('id', $requestIds->all())
            ->update([
                'status' => ApprovalRequest::StatusExpired,
                'review_note' => $resubmissionMessage,
                'expires_at' => $now,
                'updated_at' => $now,
            ]);

        if (! Schema::hasTable('approval_assignments')) {
            return;
        }

        DB::table('approval_assignments')
            ->whereIn('approval_request_id', $requestIds->all())
            ->where('status', ApprovalAssignment::StatusPending)
            ->update([
                'status' => ApprovalAssignment::StatusCancelled,
                'updated_at' => $now,
            ]);
    }
};
