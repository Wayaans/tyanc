<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Imports\SubmitUsersImport;
use App\Http\Requests\Tyanc\StoreImportRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ImportController
{
    public function store(StoreImportRequest $request, #[CurrentUser] User $user, SubmitUsersImport $action): RedirectResponse|JsonResponse
    {
        throw_unless((bool) config('tyanc.features.imports_enabled', false), NotFoundHttpException::class);

        $payload = $action->handle(
            actor: $user,
            file: $request->file('file'),
            requestNote: $request->validated('request_note'),
        );

        if ($request->wantsJson()) {
            return response()->json($payload, $payload['executed'] ? 201 : 202);
        }

        return $payload['executed']
            ? to_route('tyanc.users.index')
            : to_route('cumpu.approvals.my-requests');
    }
}
