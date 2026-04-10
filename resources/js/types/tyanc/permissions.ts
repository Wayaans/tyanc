import type { DataTablePayload } from '../admin';

export type PermissionSyncStatus = 'synced' | 'orphaned' | 'missing';

export type PermissionData = {
    id: number | null;
    name: string;
    guard_name: string;
    app: string;
    app_label: string;
    resource: string;
    resource_label: string;
    action: string;
    action_label: string;
    exists_in_source: boolean;
    exists_in_database: boolean;
    is_reserved: boolean;
    role_count: number;
    roles: string[];
    sync_status: PermissionSyncStatus;
    created_at: string | null;
    updated_at: string | null;
};

export type PermissionRow = PermissionData;

export type PermissionSyncSummary = {
    total: number;
    synced: number;
    orphaned: number;
    missing: number;
    last_synced_at: string | null;
};

export type PermissionsTablePayload = DataTablePayload<PermissionRow> & {
    summary: PermissionSyncSummary;
};
