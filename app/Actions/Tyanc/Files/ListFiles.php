<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Files\ManagedFileData;
use App\Http\Requests\Tyanc\FileIndexRequest;
use App\Models\App;
use App\Models\ManagedFile;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListFiles
{
    public function __construct(private PermissionResourceAccess $permissionAccess) {}

    /**
     * @return array{
     *     rows: array<int, ManagedFileData>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>
     * }
     */
    public function handle(User $actor, FileIndexRequest $request): array
    {
        throw_if(
            ! $this->permissionAccess->handle($actor, PermissionKey::tyanc('files', 'viewany')),
            AuthorizationException::class,
        );

        $tableQuery = $request->tableQuery();
        $queryRequest = $request->duplicate([
            ...$request->query(),
            'sort' => implode(',', $tableQuery->sort),
        ]);
        $appLabels = $this->appLabels();

        $files = QueryBuilder::for(
            subject: ManagedFile::query(),
            request: $queryRequest,
        )
            ->allowedFilters(
                AllowedFilter::callback('search', $this->applySearch(...)),
                AllowedFilter::callback('mime_group', $this->applyMimeGroup(...)),
                AllowedFilter::exact('app_key'),
                AllowedFilter::exact('folder_path'),
                AllowedFilter::exact('source'),
            )
            ->allowedSorts(
                AllowedSort::field('name', 'name'),
                AllowedSort::field('file_name', 'file_name'),
                AllowedSort::field('app_key', 'app_key'),
                AllowedSort::field('folder_path', 'folder_path'),
                AllowedSort::field('mime_type', 'mime_type'),
                AllowedSort::field('size', 'size_bytes'),
                AllowedSort::field('created_at', 'uploaded_at'),
            )
            ->defaultSort('-uploaded_at')
            ->paginate(
                perPage: $tableQuery->per_page,
                page: $tableQuery->page,
            )
            ->withQueryString();

        return [
            'rows' => $files->getCollection()
                ->map(fn (ManagedFile $file): ManagedFileData => ManagedFileData::fromModel($file, $appLabels))
                ->all(),
            'meta' => $this->meta($files),
            'query' => $tableQuery->withPage($files->currentPage()),
            'filters' => $this->filters($appLabels),
        ];
    }

    /**
     * @param  Builder<ManagedFile>  $query
     */
    private function applySearch(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        $search = mb_trim((string) $value);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('name', 'like', sprintf('%%%s%%', $search))
                ->orWhere('file_name', 'like', sprintf('%%%s%%', $search))
                ->orWhere('mime_type', 'like', sprintf('%%%s%%', $search))
                ->orWhere('folder_path', 'like', sprintf('%%%s%%', $search))
                ->orWhere('relative_path', 'like', sprintf('%%%s%%', $search))
                ->orWhere('uploaded_by_name', 'like', sprintf('%%%s%%', $search))
                ->orWhere('subject_label', 'like', sprintf('%%%s%%', $search));
        });
    }

    /**
     * @param  Builder<ManagedFile>  $query
     */
    private function applyMimeGroup(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        $group = mb_trim((string) $value);

        if ($group === '' || $group === 'all') {
            return;
        }

        if ($group === 'other') {
            $query->whereNotIn('mime_group', ['image', 'application', 'text', 'audio', 'video']);

            return;
        }

        $query->where('mime_group', $group);
    }

    /**
     * @param  array<string, string>  $appLabels
     * @return array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>
     */
    private function filters(array $appLabels): array
    {
        $appOptions = ManagedFile::query()
            ->select('app_key')
            ->distinct()
            ->orderBy('app_key')
            ->pluck('app_key')
            ->map(fn (string $appKey): array => [
                'label' => $appLabels[$appKey] ?? $this->labelize($appKey),
                'value' => $appKey,
            ])
            ->values()
            ->all();

        return [
            [
                'id' => 'search',
                'label' => 'Files',
                'type' => 'text',
                'placeholder' => 'Search files, folders, or subjects',
            ],
            [
                'id' => 'app_key',
                'label' => 'App',
                'type' => 'select',
                'options' => $appOptions,
            ],
            [
                'id' => 'mime_group',
                'label' => 'Type',
                'type' => 'select',
                'options' => [
                    ['label' => 'All files', 'value' => 'all'],
                    ['label' => 'Images', 'value' => 'image'],
                    ['label' => 'Documents', 'value' => 'application'],
                    ['label' => 'Text', 'value' => 'text'],
                    ['label' => 'Audio', 'value' => 'audio'],
                    ['label' => 'Video', 'value' => 'video'],
                    ['label' => 'Other', 'value' => 'other'],
                ],
            ],
            [
                'id' => 'source',
                'label' => 'Source',
                'type' => 'select',
                'options' => [
                    ['label' => 'Media library', 'value' => ManagedFile::SourceMediaLibrary],
                    ['label' => 'Public disk', 'value' => ManagedFile::SourcePublicDisk],
                ],
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, ManagedFile>  $paginator
     * @return array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool}
     */
    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'has_pages' => $paginator->hasPages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function appLabels(): array
    {
        return array_replace(
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
            [ManagedFile::UnassignedAppKey => 'Unassigned'],
        );
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
