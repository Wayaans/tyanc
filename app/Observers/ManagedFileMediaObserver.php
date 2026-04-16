<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Tyanc\Files\SyncManagedFiles;
use App\Models\FileLibrary;
use App\Models\ManagedFile;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class ManagedFileMediaObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private SyncManagedFiles $syncManagedFiles) {}

    public function created(Media $media): void
    {
        $this->sync($media);
    }

    public function deleted(Media $media): void
    {
        $this->sync($media);
    }

    private function sync(Media $media): void
    {
        if (! $this->shouldSync($media)) {
            return;
        }

        $this->syncManagedFiles->handle();
    }

    private function shouldSync(Media $media): bool
    {
        return $media->disk === ManagedFile::PublicDisk
            && $media->model_type !== FileLibrary::class;
    }
}
