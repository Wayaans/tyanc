<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class ExecuteApprovalControlledAction
{
    public function __construct(
        private DetectApprovalMode $approvalMode,
        private SubmitGovernedAction $governedActions,
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
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool, mode: string, requires_draft_submission: bool}
     */
    public function handle(
        User $actor,
        string $permissionName,
        ?Model $subject = null,
        array $context = [],
        array $definition = [],
    ): array {
        $mode = $this->approvalMode->handle($actor, $permissionName, $subject, $context);

        if ($mode === ApprovalMode::None) {
            $execute = $definition['execute'] ?? null;

            if (! $execute instanceof Closure) {
                throw new RuntimeException(__('The governed action is not executable.'));
            }

            return [
                'executed' => true,
                'result' => $execute(),
                'approval' => null,
                'bypassed' => false,
                'mode' => $mode->value,
                'requires_draft_submission' => false,
            ];
        }

        if ($mode === ApprovalMode::Draft) {
            return [
                'executed' => false,
                'result' => null,
                'approval' => null,
                'bypassed' => false,
                'mode' => $mode->value,
                'requires_draft_submission' => true,
            ];
        }

        $submission = $this->governedActions->handle(
            actor: $actor,
            permissionName: $permissionName,
            subject: $subject,
            context: $context,
            definition: $definition,
        );

        return [
            ...$submission,
            'mode' => $mode->value,
            'requires_draft_submission' => false,
        ];
    }
}
