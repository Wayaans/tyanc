import { createColumnHelper, type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { __ } from '@/lib/translations';
import type { MediaFileRow } from '@/types';

const columnHelper = createColumnHelper<MediaFileRow>();

function mimeTypeLabel(mime: string | null): string {
    if (!mime) {
        return '—';
    }
    const parts = mime.split('/');

    return parts[1]?.toUpperCase() ?? mime;
}

export function createFileTableColumns(
    dateFormatter: Intl.DateTimeFormat,
    onPreview: (file: MediaFileRow) => void,
): ColumnDef<MediaFileRow>[] {
    return [
        columnHelper.display({
            id: 'select',
            enableSorting: false,
            enableHiding: false,
            header: ({ table }) =>
                h(Checkbox, {
                    modelValue: table.getIsAllPageRowsSelected()
                        ? true
                        : table.getIsSomePageRowsSelected()
                          ? 'indeterminate'
                          : false,
                    'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                        table.toggleAllPageRowsSelected(Boolean(value)),
                    'aria-label': __('Select all rows'),
                }),
            cell: ({ row }) =>
                h(Checkbox, {
                    modelValue: row.getIsSelected(),
                    'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                        row.toggleSelected(Boolean(value)),
                    'aria-label': __('Select row'),
                }),
            meta: { label: 'Selection' },
        }),

        columnHelper.accessor('file_name', {
            header: __('Files'),
            enableSorting: true,
            cell: ({ row }) =>
                h(
                    'button',
                    {
                        class: 'flex items-center gap-3 min-w-52 text-left group',
                        onClick: () => onPreview(row.original),
                    },
                    [
                        h(
                            'div',
                            {
                                class: 'size-10 shrink-0 rounded-lg overflow-hidden border border-sidebar-border/70 bg-muted flex items-center justify-center',
                            },
                            row.original.preview_url
                                ? h('img', {
                                      src: row.original.preview_url,
                                      alt: row.original.file_name,
                                      class: 'size-full object-cover',
                                  })
                                : h(
                                      'span',
                                      {
                                          class: 'text-xs text-muted-foreground font-mono',
                                      },
                                      mimeTypeLabel(row.original.mime_type),
                                  ),
                        ),
                        h('div', { class: 'space-y-0.5 min-w-0' }, [
                            h(
                                'p',
                                {
                                    class: 'font-medium text-foreground leading-none truncate group-hover:text-primary transition-colors',
                                },
                                row.original.name,
                            ),
                            h(
                                'p',
                                {
                                    class: 'text-xs text-muted-foreground truncate',
                                },
                                row.original.file_name,
                            ),
                        ]),
                    ],
                ),
            meta: { label: 'File' },
        }),

        columnHelper.accessor('mime_type', {
            header: __('Type'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    Badge,
                    {
                        variant: 'outline',
                        class: 'rounded-full text-xs font-mono',
                    },
                    { default: () => mimeTypeLabel(getValue()) },
                ),
            meta: { label: 'Type' },
        }),

        columnHelper.accessor('size_human', {
            header: __('File size'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground tabular-nums' },
                    String(getValue()),
                ),
            meta: { label: 'File size' },
        }),

        columnHelper.accessor('uploaded_by_name', {
            header: __('Uploaded by'),
            enableSorting: false,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    String(getValue() ?? '—'),
                ),
            meta: { label: 'Uploaded by' },
        }),

        columnHelper.accessor('created_at', {
            header: __('Created at'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    {
                        class: 'whitespace-nowrap text-xs text-muted-foreground',
                    },
                    dateFormatter.format(new Date(String(getValue()))),
                ),
            meta: { label: 'Uploaded' },
        }),
    ] as ColumnDef<MediaFileRow>[];
}
