<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\Tyanc\Activity\ActivityLogEntryData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Exportable;
use Spatie\Activitylog\Models\Activity;

final class ActivityLogExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function collection()
    {
        return Activity::query()
            ->with(['subject', 'causer'])
            ->latest('created_at')
            ->get();
    }

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
