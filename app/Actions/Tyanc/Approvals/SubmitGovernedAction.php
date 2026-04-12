<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class SubmitGovernedAction
{
    public function __construct(
        private ResolveApprovalRule $rules,
        private ShouldBypassApproval $bypassApproval,
        private CreateApprovalProposal $approvalRequests,
        private ConsumeApprovalGrant $consumeApprovalGrant,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array{
     *     execute?: Closure(): mixed,
     *     proposal?: array{
     *         request_note?: string|null,
     *         payload?: array{action_label?: string|null, subject_label?: string|null}|null,
     *         subject_snapshot?: array<string, mixed>|null,
     *     },
     * }  $definition
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool}
     */
    public function handle(
        User $actor,
        string $permissionName,
        ?Model $subject = null,
        array $context = [],
        array $definition = [],
    ): array {
        $execute = $definition['execute'] ?? null;

        if (! $execute instanceof Closure) {
            throw new RuntimeException(__('The governed action is not executable.'));
        }

        $grantConsumption = $this->consumeApprovalGrant->handle(
            actor: $actor,
            permissionName: $permissionName,
            subject: $subject,
            execute: $execute,
        );

        if ((bool) $grantConsumption['consumed']) {
            return [
                'executed' => true,
                'result' => $grantConsumption['result'],
                'approval' => null,
                'bypassed' => false,
            ];
        }

        $rule = $this->rules->handle($actor, $permissionName, $subject, $context);

        if (! $rule instanceof ApprovalRule) {
            return [
                'executed' => true,
                'result' => $execute(),
                'approval' => null,
                'bypassed' => false,
            ];
        }

        if ($this->bypassApproval->handle($actor, $rule)) {
            $result = $execute();

            activity('approvals')
                ->performedOn($subject ?? $actor)
                ->causedBy($actor)
                ->event('bypassed')
                ->withProperties([
                    'permission_name' => $permissionName,
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => $subject instanceof Model && is_scalar($subject->getKey()) ? (string) $subject->getKey() : null,
                ])
                ->log('Approval bypassed and action executed');

            return [
                'executed' => true,
                'result' => $result,
                'approval' => null,
                'bypassed' => true,
            ];
        }

        $requestNote = data_get($definition, 'proposal.request_note');

        if (! is_string($requestNote) || mb_trim($requestNote) === '') {
            throw ValidationException::withMessages([
                'request_note' => __('Provide a reason before requesting approval.'),
            ]);
        }

        $approvalRequest = $this->approvalRequests->handle(
            actor: $actor,
            rule: $rule,
            permissionName: $permissionName,
            subject: $subject,
            attributes: is_array($definition['proposal'] ?? null) ? $definition['proposal'] : [],
        );

        return [
            'executed' => false,
            'result' => null,
            'approval' => $approvalRequest,
            'bypassed' => false,
        ];
    }
}
