<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Imports;

use App\Jobs\ProcessUsersImport;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class QueueUsersImport
{
    public function handle(User $actor, UploadedFile|string $file, ?string $originalName = null): ImportRun
    {
        return DB::transaction(function () use ($actor, $file, $originalName): ImportRun {
            $fileName = $file instanceof UploadedFile
                ? $file->getClientOriginalName()
                : ($originalName ?? basename($file));

            $importRun = ImportRun::query()->create([
                'type' => ImportRun::TypeUsers,
                'status' => ImportRun::StatusQueued,
                'file_name' => $fileName,
                'processed_rows' => 0,
                'created_by_id' => $actor->id,
                'failure_message' => null,
            ]);

            if ($file instanceof UploadedFile) {
                $importRun
                    ->addMedia($file)
                    ->usingFileName($fileName)
                    ->withCustomProperties([
                        'app_key' => 'tyanc',
                        'resource_key' => 'users',
                        'folder_path' => 'tyanc/users/imports',
                        'subject_label' => 'Users import',
                        'uploaded_by_id' => (string) $actor->id,
                        'uploaded_by_name' => $actor->name,
                    ])
                    ->toMediaCollection(ImportRun::SourceFileCollection);
            } else {
                $absolutePath = Storage::disk('local')->path($file);

                if (! is_file($absolutePath)) {
                    throw new RuntimeException(__('The staged import file could not be found.'));
                }

                $importRun
                    ->addMedia($absolutePath)
                    ->usingFileName($fileName)
                    ->withCustomProperties([
                        'app_key' => 'tyanc',
                        'resource_key' => 'users',
                        'folder_path' => 'tyanc/users/imports',
                        'subject_label' => 'Users import',
                        'uploaded_by_id' => (string) $actor->id,
                        'uploaded_by_name' => $actor->name,
                    ])
                    ->toMediaCollection(ImportRun::SourceFileCollection);

                Storage::disk('local')->delete($file);
            }

            activity('imports')
                ->performedOn($importRun)
                ->causedBy($actor)
                ->event('queued')
                ->withProperties([
                    'attributes' => [
                        'file_name' => $fileName,
                        'status' => ImportRun::StatusQueued,
                    ],
                ])
                ->log('Users import queued');

            dispatch(new ProcessUsersImport((string) $importRun->id))->afterCommit();

            return $importRun->fresh(['creator', 'approvalRequests']);
        });
    }
}
