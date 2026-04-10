<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Files\DeleteFile;
use App\Actions\Tyanc\Files\ListFiles;
use App\Actions\Tyanc\Files\UploadFile;
use App\Data\Tyanc\Files\MediaFileData;
use App\Http\Requests\Tyanc\FileIndexRequest;
use App\Http\Requests\Tyanc\UploadFileRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class FileController
{
    public function index(FileIndexRequest $request, #[CurrentUser] User $user, ListFiles $action): Response|JsonResponse
    {
        $payload = [
            'filesTable' => $action->handle($user, $request),
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
                    MediaFileData::fromModel(...),
                    $files,
                ),
            ], 201);
        }

        return to_route('tyanc.files.index');
    }

    public function destroy(Request $request, #[CurrentUser] User $user, Media $media, DeleteFile $action): RedirectResponse|JsonResponse
    {
        $action->handle($user, $media);

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('tyanc.files.index');
    }
}
