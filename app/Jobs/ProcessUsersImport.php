<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Imports\UsersImport;
use App\Models\ImportRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

#[Timeout(120)]
#[Tries(3)]
final class ProcessUsersImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $importRunId) {}

    public function handle(): void
    {
        $importRun = ImportRun::query()
            ->with(['creator', 'media'])
            ->find($this->importRunId);

        if (! $importRun instanceof ImportRun) {
            return;
        }

        $media = $importRun->getFirstMedia(ImportRun::SourceFileCollection);

        if (! $media instanceof Media) {
            throw new RuntimeException(__('The import source file could not be found.'));
        }

        $importRun->forceFill([
            'status' => ImportRun::StatusProcessing,
            'started_at' => now(),
            'finished_at' => null,
            'failure_message' => null,
        ])->save();

        Excel::import(new UsersImport((string) $importRun->id), $media->getPath());

        $importRun->refresh();
        $importRun->forceFill([
            'status' => ImportRun::StatusCompleted,
            'finished_at' => now(),
        ])->save();

        activity('imports')
            ->performedOn($importRun)
            ->causedBy($importRun->creator)
            ->event('completed')
            ->withProperties([
                'processed_rows' => $importRun->processed_rows,
            ])
            ->log('Users import completed');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function failed(?Throwable $exception): void
    {
        $importRun = ImportRun::query()->with('creator')->find($this->importRunId);

        if (! $importRun instanceof ImportRun) {
            return;
        }

        $importRun->forceFill([
            'status' => ImportRun::StatusFailed,
            'failure_message' => $exception?->getMessage(),
            'finished_at' => now(),
        ])->save();

        activity('imports')
            ->performedOn($importRun)
            ->causedBy($importRun->creator)
            ->event('failed')
            ->withProperties([
                'failure_message' => $exception?->getMessage(),
            ])
            ->log('Users import failed');
    }
}
