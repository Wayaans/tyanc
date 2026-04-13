<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class TyancBootstrapIncomplete extends RuntimeException
{
    /**
     * @param  list<string>  $missing
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $missing,
        public readonly array $warnings = [],
        ?string $message = null,
        private readonly string $command = 'php artisan tyanc:bootstrap --no-interaction',
        private readonly string $superAdminCommand = 'php artisan tyanc:create-super-admin',
    ) {
        parent::__construct($message ?? 'Tyanc bootstrap is incomplete.');
    }

    /**
     * @param  array{ready: bool, missing: list<string>, warnings: list<string>}  $status
     */
    public static function fromStatus(array $status, ?string $message = null): self
    {
        return new self(
            missing: $status['missing'],
            warnings: $status['warnings'],
            message: $message,
        );
    }

    /**
     * @param  list<string>  $missing
     * @param  list<string>  $warnings
     */
    public static function forMissing(array $missing, array $warnings = [], ?string $message = null): self
    {
        return new self(
            missing: $missing,
            warnings: $warnings,
            message: $message,
        );
    }

    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 503,
                'code' => 'bootstrap_incomplete',
                'message' => $this->getMessage(),
                'missing' => $this->missing,
                'warnings' => $this->warnings,
                'command' => $this->command,
            ], 503);
        }

        return Inertia::render('errors/BootstrapIncomplete', [
            'message' => $this->getMessage(),
            'missing' => $this->missing,
            'warnings' => $this->warnings,
            'command' => $this->command,
            'superAdminCommand' => $this->superAdminCommand,
        ])->toResponse($request)->setStatusCode(503);
    }

    /**
     * @return array{missing: list<string>, warnings: list<string>, command: string}
     */
    public function context(): array
    {
        return [
            'missing' => $this->missing,
            'warnings' => $this->warnings,
            'command' => $this->command,
        ];
    }
}
