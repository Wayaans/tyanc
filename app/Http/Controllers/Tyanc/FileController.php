<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Files\DeleteFile;
use App\Actions\Tyanc\Files\DownloadFile;
use App\Actions\Tyanc\Files\ListFiles;
use App\Actions\Tyanc\Files\ResolveFileExplorer;
use App\Actions\Tyanc\Files\StreamFile;
use App\Actions\Tyanc\Files\SyncManagedFiles;
use App\Actions\Tyanc\Files\UploadFile;
use App\Data\Tyanc\Files\ManagedFileData;
use App\Http\Requests\Tyanc\FileIndexRequest;
use App\Http\Requests\Tyanc\UploadFileRequest;
use App\Models\ManagedFile;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class FileController
{
    public function index(
        FileIndexRequest $request,
        #[CurrentUser] User $user,
        SyncManagedFiles $syncManagedFiles,
        ListFiles $listFiles,
        ResolveFileExplorer $resolveFileExplorer,
        PermissionResourceAccess $permissionAccess,
    ): Response|JsonResponse {
        $syncManagedFiles->handle();

        $payload = [
            'filesTable' => $listFiles->handle($user, $request),
            'explorer' => $resolveFileExplorer->handle(),
            'abilities' => [
                'download' => $permissionAccess->handle($user, PermissionKey::tyanc('files', 'download')),
                'delete' => $permissionAccess->handle($user, PermissionKey::tyanc('files', 'delete')),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/files/Index', $payload);
    }

    public function store(UploadFileRequest $request, #[CurrentUser] User $user, UploadFile $action): RedirectResponse|JsonResponse
    {
        $files = $action->handle($user, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'files' => array_map(
                    ManagedFileData::fromModel(...),
                    $files,
                ),
            ], 201);
        }

        return to_route('tyanc.files.index');
    }

    public function show(#[CurrentUser] User $user, ManagedFile $managedFile, StreamFile $action): StreamedResponse
    {
        return $action->handle($user, $managedFile);
    }

    public function download(#[CurrentUser] User $user, ManagedFile $managedFile, DownloadFile $action): StreamedResponse
    {
        return $action->handle($user, $managedFile);
    }

    public function destroy(Request $request, #[CurrentUser] User $user, ManagedFile $managedFile, DeleteFile $action): RedirectResponse|JsonResponse
    {
        $action->handle($user, $managedFile);

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('tyanc.files.index');
    }
}
