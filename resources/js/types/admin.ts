import type { RowData } from '@tanstack/vue-table';

// Augment TanStack column meta so DataTableViewOptions can show human-readable labels.
declare module '@tanstack/vue-table' {
    interface ColumnMeta<TData extends RowData, TValue> {
        label?: string;
    }
}

export type DataTablePrimitive = string | number | boolean | null;

export type DataTableQuery = {
    page: number;
    per_page: number;
    sort: string[];
    filter: Record<string, string | string[]>;
    columns: Record<string, boolean>;
};

export type DataTableMeta = {
    total: number;
    from: number | null;
    to: number | null;
    page: number;
    per_page: number;
    last_page: number;
    has_pages: boolean;
};

export type DataTableFilterOption = {
    label: string;
    value: string;
};

export type DataTableFilterDefinition = {
    id: string;
    label: string;
    type: 'text' | 'select';
    placeholder?: string;
    options?: DataTableFilterOption[];
};

export type DataTablePayload<TRow extends Record<string, DataTablePrimitive>> =
    {
        rows: TRow[];
        meta: DataTableMeta;
        query: DataTableQuery;
        filters: DataTableFilterDefinition[];
    };
