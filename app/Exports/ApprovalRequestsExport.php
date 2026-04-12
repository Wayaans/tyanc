<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\Tyanc\Approvals\ApprovalReportRowData;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** @implements WithMapping<ApprovalReportRowData> */
final readonly class ApprovalRequestsExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @param  Collection<int, ApprovalReportRowData>  $rows
     */
    public function __construct(private Collection $rows) {}

    /**
     * @return Collection<int, ApprovalReportRowData>
     */
    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  ApprovalReportRowData  $row
     * @return array<int, bool|float|int|string|null>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->app_key,
            $row->resource_key,
            $row->action_key,
            $row->action_label,
            $row->status,
            $row->subject_name,
            $row->requested_by_name,
            $row->reviewed_by_name,
            implode(', ', $row->current_assignee_names),
            $row->current_step_label,
            $row->current_step_order,
            $row->is_overdue ? __('Yes') : __('No'),
            $row->is_reassigned ? __('Yes') : __('No'),
            $row->is_escalated ? __('Yes') : __('No'),
            $row->requested_at,
            $row->reviewed_at,
            $row->turnaround_hours,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('Request ID'),
            __('App'),
            __('Resource'),
            __('Action'),
            __('Action label'),
            __('Status'),
            __('Subject'),
            __('Requester'),
            __('Reviewer'),
            __('Current assignees'),
            __('Current step'),
            __('Current step order'),
            __('Overdue'),
            __('Reassigned'),
            __('Escalated'),
            __('Requested at'),
            __('Reviewed at'),
            __('Turnaround hours'),
        ];
    }
}
