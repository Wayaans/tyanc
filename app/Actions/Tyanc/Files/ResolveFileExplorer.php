<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Data\Tyanc\Files\FileExplorerAppData;
use App\Data\Tyanc\Files\FileExplorerData;
use App\Data\Tyanc\Files\FileExplorerFolderData;
use App\Models\App;
use App\Models\ManagedFile;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final readonly class ResolveFileExplorer
{
    public function handle(): FileExplorerData
    {
        $appLabels = array_replace(
            collect((array) config('sidebar-menu.apps', []))
                ->mapWithKeys(fn (array $app, string $key): array => [
                    $key => (string) ($app['title'] ?? Str::of($key)->title()->value()),
                ])
                ->all(),
            App::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->pluck('label', 'key')
                ->all(),
        );

        $files = ManagedFile::query()
            ->orderBy('app_key')
            ->orderBy('folder_path')
            ->latest('uploaded_at')
            ->get();

        $apps = $files
            ->groupBy('app_key')
            ->map(fn ($group, string $appKey): FileExplorerAppData => new FileExplorerAppData(
                key: $appKey,
                label: $appLabels[$appKey] ?? $this->labelize($appKey),
                total_files: $group->count(),
                folders: $group
                    ->groupBy('folder_path')
                    ->map(fn ($folder, string $folderPath): FileExplorerFolderData => new FileExplorerFolderData(
                        path: $folderPath,
                        label: $this->folderLabel($folderPath),
                        total_files: $folder->count(),
                    ))
                    ->sortBy(fn (FileExplorerFolderData $folder): string => $folder->path)
                    ->values()
                    ->all(),
            ))
            ->sortBy(fn (FileExplorerAppData $app): string => $app->label)
            ->values()
            ->all();

        return new FileExplorerData(
            total_files: $files->count(),
            total_size_bytes: (int) $files->sum('size_bytes'),
            total_size_human: Number::fileSize((int) $files->sum('size_bytes')),
            app_count: count($apps),
            folder_count: $files->pluck('folder_path')->unique()->count(),
            media_files: $files->where('source', ManagedFile::SourceMediaLibrary)->count(),
            public_files: $files->where('source', ManagedFile::SourcePublicDisk)->count(),
            apps: $apps,
        );
    }

    private function folderLabel(string $folderPath): string
    {
        return collect(explode('/', $folderPath))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->skip(1)
            ->map(fn (string $segment): string => $this->labelize($segment))
            ->whenEmpty(fn ($segments) => $segments->push('Root'))
            ->implode(' / ');
    }

    private function labelize(string $value): string
    {
        return Str::of($value)
            ->replace(['-', '_'], ' ')
            ->trim()
            ->title()
            ->value();
    }
}
