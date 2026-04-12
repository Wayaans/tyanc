<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\Tyanc\Activity\ActivityLogEntryData;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\Activitylog\Models\Activity;

/** @implements WithMapping<Activity> */
final class ActivityLogExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @return Collection<int, Activity>
     */
    public function collection(): Collection
    {
        return Activity::query()
            ->with(['subject', 'causer'])
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  Activity  $activity
     * @return array<int, string|null>
     */
    public function map($activity): array
    {
        $entry = ActivityLogEntryData::fromModel($activity);

        return [
            $entry->log_name,
            $entry->event,
            $entry->description,
            $entry->subject_name,
            $entry->causer_name,
            $entry->created_at,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('Log'),
            __('Event'),
            __('Description'),
            __('Subject'),
            __('Caused by'),
            __('When'),
        ];
    }
}
