<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class SubmitApprovalRequest
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, string $action, Model $subject, array $attributes = []): ApprovalRequest
    {
        /** @var ApprovalRequest|null $existing */
        $existing = ApprovalRequest::query()
            ->where('action', $action)
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('status', ApprovalRequest::StatusPending)
            ->first();

        if ($existing instanceof ApprovalRequest) {
            return $existing->loadMissing('requester', 'reviewer', 'subject');
        }

        return DB::transaction(function () use ($actor, $action, $subject, $attributes): ApprovalRequest {
            $approvalRequest = ApprovalRequest::query()->create([
                'action' => $action,
                'status' => ApprovalRequest::StatusPending,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'requested_by_id' => $actor->id,
                'request_note' => $this->nullableString($attributes['request_note'] ?? null),
                'payload' => is_array($attributes['payload'] ?? null) ? $attributes['payload'] : null,
                'requested_at' => now(),
            ]);

            activity('approvals')
                ->performedOn($subject)
                ->causedBy($actor)
                ->event('requested')
                ->withProperties([
                    'attributes' => $approvalRequest->toArray(),
                ])
                ->log('Approval requested');

            $reviewers = $this->reviewers();

            foreach ($reviewers as $reviewer) {
                if ($reviewer->is($actor)) {
                    continue;
                }

                $reviewer->notify(new NewApprovalRequestedNotification($approvalRequest));
            }

            return $approvalRequest->loadMissing('requester', 'reviewer', 'subject');
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function reviewers(): Collection
    {
        return User::query()
            ->with(['roles.permissions', 'permissions'])
            ->get()
            ->filter(function (User $user): bool {
                if ($user->hasRole(config('tyanc.reserved_roles.super_admin'))) {
                    return true;
                }

                return resolve(PermissionResourceAccess::class)->handle(
                    $user,
                    PermissionKey::tyanc('approvals', 'approve'),
                );
            })
            ->values();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
