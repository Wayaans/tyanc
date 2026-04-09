<?php

declare(strict_types=1);

namespace App\Http\Controllers\Demo;

use App\Data\Tables\DataTableQueryData;
use App\Support\Tables\AppliesTableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DashboardController
{
    public function __construct(private AppliesTableQuery $tableQuery) {}

    public function show(Request $request): Response|JsonResponse
    {
        $query = DataTableQueryData::fromRequest(
            request: $request,
            allowedSorts: ['name', 'category', 'maturity', 'surface', 'updated_at'],
            allowedFilters: ['name', 'category', 'maturity'],
            defaultSort: ['name'],
            allowedColumns: ['name', 'category', 'maturity', 'surface', 'updated_at'],
        );

        $payload = [
            'examplesTable' => [
                ...$this->tableQuery->handle(
                    items: $this->rows(),
                    query: $query,
                    sorts: [
                        'name' => 'name',
                        'category' => 'category',
                        'maturity' => 'maturity',
                        'surface' => 'surface',
                        'updated_at' => 'updated_at',
                    ],
                    filters: [
                        'name' => 'name',
                        'category' => 'category',
                        'maturity' => 'maturity',
                    ],
                ),
                'filters' => $this->filters(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('demo/Dashboard', $payload);
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'Component',
                'type' => 'text',
                'placeholder' => 'Filter components',
            ],
            [
                'id' => 'category',
                'label' => 'Category',
                'type' => 'select',
                'options' => [
                    ['label' => 'Dashboard', 'value' => 'Dashboard'],
                    ['label' => 'Forms', 'value' => 'Forms'],
                    ['label' => 'Feedback', 'value' => 'Feedback'],
                ],
            ],
            [
                'id' => 'maturity',
                'label' => 'Maturity',
                'type' => 'select',
                'options' => [
                    ['label' => 'Ready', 'value' => 'Ready'],
                    ['label' => 'Beta', 'value' => 'Beta'],
                    ['label' => 'Preview', 'value' => 'Preview'],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, array{
     *     id: string,
     *     name: string,
     *     category: string,
     *     maturity: string,
     *     surface: string,
     *     updated_at: string
     * }>
     */
    private function rows(): Collection
    {
        return collect([
            [
                'id' => 'analytics-card',
                'name' => 'Analytics card',
                'category' => 'Dashboard',
                'maturity' => 'Ready',
                'surface' => 'Card grid',
                'updated_at' => '2026-04-09T09:00:00+08:00',
            ],
            [
                'id' => 'approval-dialog',
                'name' => 'Approval dialog',
                'category' => 'Forms',
                'maturity' => 'Ready',
                'surface' => 'Dialog',
                'updated_at' => '2026-04-09T08:20:00+08:00',
            ],
            [
                'id' => 'appearance-sheet',
                'name' => 'Appearance sheet',
                'category' => 'Forms',
                'maturity' => 'Beta',
                'surface' => 'Sheet',
                'updated_at' => '2026-04-09T07:40:00+08:00',
            ],
            [
                'id' => 'navigation-switcher',
                'name' => 'Navigation switcher',
                'category' => 'Dashboard',
                'maturity' => 'Preview',
                'surface' => 'Navigation',
                'updated_at' => '2026-04-08T19:10:00+08:00',
            ],
            [
                'id' => 'toast-feedback',
                'name' => 'Toast feedback',
                'category' => 'Feedback',
                'maturity' => 'Ready',
                'surface' => 'Notification',
                'updated_at' => '2026-04-08T17:50:00+08:00',
            ],
        ]);
    }
}
