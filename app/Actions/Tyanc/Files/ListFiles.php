<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Files\MediaFileData;
use App\Http\Requests\Tyanc\FileIndexRequest;
use App\Models\FileLibrary;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListFiles
{
    /**
     * @return array{
     *     rows: array<int, MediaFileData>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>
     * }
     */
    public function handle(User $actor, FileIndexRequest $request): array
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('files', 'viewany')),
            AuthorizationException::class,
        );

        $library = FileLibrary::shared();
        $tableQuery = $request->tableQuery();
        $queryRequest = $request->duplicate([
            ...$request->query(),
            'sort' => implode(',', $tableQuery->sort),
        ]);

        $files = QueryBuilder::for(
            subject: Media::query()
                ->where('model_type', FileLibrary::class)
                ->where('model_id', $library->id),
            request: $queryRequest,
        )
            ->allowedFilters(
                AllowedFilter::callback('search', $this->applySearch(...)),
                AllowedFilter::callback('mime_group', $this->applyMimeGroup(...)),
            )
            ->allowedSorts(
                AllowedSort::field('name', 'name'),
                AllowedSort::field('file_name', 'file_name'),
                AllowedSort::field('mime_type', 'mime_type'),
                AllowedSort::field('size', 'size'),
                AllowedSort::field('created_at', 'created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate(
                perPage: $tableQuery->per_page,
                page: $tableQuery->page,
            )
            ->withQueryString();

        return [
            'rows' => $files->getCollection()
                ->map(fn (Media $media): MediaFileData => MediaFileData::fromModel($media))
                ->all(),
            'meta' => $this->meta($files),
            'query' => $tableQuery->withPage($files->currentPage()),
            'filters' => $this->filters(),
        ];
    }

    /**
     * @param  Builder<Media>  $query
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
                ->orWhere('mime_type', 'like', sprintf('%%%s%%', $search));
        });
    }

    /**
     * @param  Builder<Media>  $query
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
            $query->where(function (Builder $builder): void {
                $builder
                    ->where('mime_type', 'not like', 'image/%')
                    ->where('mime_type', 'not like', 'application/%')
                    ->where('mime_type', 'not like', 'text/%')
                    ->where('mime_type', 'not like', 'audio/%')
                    ->where('mime_type', 'not like', 'video/%');
            });

            return;
        }

        $query->where('mime_type', 'like', sprintf('%s/%%', $group));
    }

    /**
     * @return array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'search',
                'label' => 'Files',
                'type' => 'text',
                'placeholder' => 'Search files',
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
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Media>  $paginator
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
}
