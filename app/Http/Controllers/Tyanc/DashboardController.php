<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

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
            allowedSorts: ['name', 'status', 'team', 'records', 'health_score', 'updated_at'],
            allowedFilters: ['name', 'status', 'team'],
            defaultSort: ['-health_score', 'name'],
            allowedColumns: ['name', 'status', 'team', 'records', 'health_score', 'updated_at'],
        );

        $payload = [
            'summary' => $this->summary(),
            'modulesTable' => [
                ...$this->tableQuery->handle(
                    items: $this->rows(),
                    query: $query,
                    sorts: [
                        'name' => 'name',
                        'status' => 'status',
                        'team' => 'team',
                        'records' => 'records',
                        'health_score' => 'health_score',
                        'updated_at' => 'updated_at',
                    ],
                    filters: [
                        'name' => 'name',
                        'status' => 'status',
                        'team' => 'team',
                    ],
                ),
                'filters' => $this->filters(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/Dashboard', $payload);
    }

    /**
     * @return array{module_count: int, attention_count: int, average_health: int}
     */
    private function summary(): array
    {
        $rows = $this->rows();
        $averageHealth = (int) round($rows->avg('health_score') ?? 0);

        return [
            'module_count' => $rows->count(),
            'attention_count' => $rows->where('status', 'Attention')->count(),
            'average_health' => $averageHealth,
        ];
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'Module',
                'type' => 'text',
                'placeholder' => 'Filter modules',
            ],
            [
                'id' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    ['label' => 'Healthy', 'value' => 'Healthy'],
                    ['label' => 'Monitoring', 'value' => 'Monitoring'],
                    ['label' => 'Attention', 'value' => 'Attention'],
                ],
            ],
            [
                'id' => 'team',
                'label' => 'Team',
                'type' => 'select',
                'options' => [
                    ['label' => 'Platform', 'value' => 'Platform'],
                    ['label' => 'Security', 'value' => 'Security'],
                    ['label' => 'Experience', 'value' => 'Experience'],
                    ['label' => 'Operations', 'value' => 'Operations'],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, array{
     *     id: string,
     *     name: string,
     *     status: string,
     *     team: string,
     *     records: int,
     *     health_score: int,
     *     updated_at: string
     * }>
     */
    private function rows(): Collection
    {
        return collect([
            [
                'id' => 'identity-foundation',
                'name' => 'Identity foundation',
                'status' => 'Healthy',
                'team' => 'Platform',
                'records' => 1248,
                'health_score' => 98,
                'updated_at' => '2026-04-09T08:30:00+08:00',
            ],
            [
                'id' => 'role-matrix',
                'name' => 'Role matrix',
                'status' => 'Healthy',
                'team' => 'Security',
                'records' => 64,
                'health_score' => 95,
                'updated_at' => '2026-04-09T07:45:00+08:00',
            ],
            [
                'id' => 'brand-runtime',
                'name' => 'Brand runtime',
                'status' => 'Monitoring',
                'team' => 'Experience',
                'records' => 18,
                'health_score' => 87,
                'updated_at' => '2026-04-09T07:10:00+08:00',
            ],
            [
                'id' => 'localization-delivery',
                'name' => 'Localization delivery',
                'status' => 'Healthy',
                'team' => 'Experience',
                'records' => 42,
                'health_score' => 93,
                'updated_at' => '2026-04-09T06:55:00+08:00',
            ],
            [
                'id' => 'demo-sandbox',
                'name' => 'Demo sandbox',
                'status' => 'Monitoring',
                'team' => 'Operations',
                'records' => 12,
                'health_score' => 82,
                'updated_at' => '2026-04-08T21:10:00+08:00',
            ],
            [
                'id' => 'import-pipeline',
                'name' => 'Import pipeline',
                'status' => 'Attention',
                'team' => 'Operations',
                'records' => 7,
                'health_score' => 72,
                'updated_at' => '2026-04-08T18:20:00+08:00',
            ],
        ]);
    }
}
