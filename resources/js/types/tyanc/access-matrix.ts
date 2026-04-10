import type { AppData } from './apps';
import type { PermissionData } from './permissions';
import type { RoleData } from './roles';

export type AccessMatrixRow = {
    id: number;
    permission: string;
    app: string | null;
    resource: string | null;
    action: string | null;
    page?: string | null;
    page_key?: string | null;
    app_label?: string | null;
    [roleKey: string]: boolean | string | number | null | undefined;
};

export type EffectiveAccessData = {
    role_id: number | null;
    role_name: string | null;
    roles: string[];
    direct_permissions: string[];
    permissions: string[];
    accessible_apps: Array<{
        id: string;
        key: string;
        label: string;
        subtitle: string;
        route_prefix: string;
        icon: string;
        permission_namespace: string;
        enabled: boolean;
        sort_order: number;
        is_system: boolean;
        href: string;
    }>;
    accessible_pages: Array<{
        app_key: string;
        app_label: string;
        page_key: string;
        page_label: string;
        permission_name: string | null;
    }>;
};

export type AccessMatrixPayload = {
    matrix: {
        rows: AccessMatrixRow[];
        meta: {
            total: number;
            from: number | null;
            to: number | null;
            page: number;
            per_page: number;
            last_page: number;
            has_pages: boolean;
        };
        query: {
            page: number;
            per_page: number;
            sort: string[];
            filter: Record<string, string | string[]>;
            columns: Record<string, boolean>;
        };
        filters: {
            id: string;
            label: string;
            type: 'text' | 'select';
            placeholder?: string;
            options?: { label: string; value: string }[];
        }[];
    };
    roles: RoleData[];
    permissions: PermissionData[];
    apps: AppData[];
    effective_preview: EffectiveAccessData | null;
};
