import type { AppData } from './apps';
import type { MediaFileRow } from './files';

export type TyancDashboardModuleStatus = 'Healthy' | 'Monitoring' | 'Attention';

export type TyancDashboardModuleMetric = {
    label: string;
    value: number | string;
};

export type TyancDashboardModule = {
    key: 'users' | 'roles' | 'permissions' | 'files' | 'apps';
    title: string;
    value: number;
    status: TyancDashboardModuleStatus;
    description: string;
    metrics: TyancDashboardModuleMetric[];
};

export type TyancDashboardSummary = {
    module_count: number;
    healthy_count: number;
    monitoring_count: number;
    attention_count: number;
};

export type TyancDashboardAbilities = {
    users: boolean;
    roles: boolean;
    permissions: boolean;
    files: boolean;
    apps: boolean;
    messages: boolean;
    activity_log: boolean;
};

export type TyancDashboardRecentUser = {
    id: string;
    name: string;
    email: string;
    avatar: string | null;
    status: string;
    roles: string[];
    created_at: string;
    last_login_at: string | null;
};

export type TyancDashboardUsers = {
    total: number;
    active: number;
    pending_verification: number;
    suspended: number;
    banned: number;
    verified: number;
    two_factor_enabled: number;
    recent: TyancDashboardRecentUser[];
};

export type TyancDashboardRole = {
    id: number;
    name: string;
    level: number;
    user_count: number;
    permission_count: number;
    is_reserved: boolean;
};

export type TyancDashboardRoles = {
    total: number;
    reserved: number;
    with_permissions: number;
    without_permissions: number;
    top: TyancDashboardRole[];
};

export type TyancDashboardPermission = {
    name: string;
    app_label: string;
    action_label: string;
    resource_label: string;
    role_count: number;
    sync_status: 'synced' | 'missing' | 'orphaned';
};

export type TyancDashboardPermissions = {
    total: number;
    source_total: number;
    synced: number;
    missing: number;
    orphaned: number;
    top: TyancDashboardPermission[];
};

export type TyancDashboardFiles = {
    total: number;
    total_size_bytes: number;
    total_size_human: string;
    recent_uploads: number;
    images: number;
    documents: number;
    recent: MediaFileRow[];
};

export type TyancDashboardApp = Pick<
    AppData,
    'id' | 'key' | 'label' | 'route_prefix' | 'enabled' | 'is_system'
> & {
    page_count: number;
};

export type TyancDashboardApps = {
    total: number;
    enabled: number;
    disabled: number;
    pages: number;
    system: number;
    recent: TyancDashboardApp[];
};

export type TyancDashboardAlert = {
    key: string;
    title: string;
    description: string;
    tone: 'danger' | 'warning' | 'info';
    target: 'users' | 'roles' | 'permissions' | 'files' | 'apps';
};

export type TyancDashboardProps = {
    summary: TyancDashboardSummary;
    abilities: TyancDashboardAbilities;
    modules: TyancDashboardModule[];
    users: TyancDashboardUsers;
    roles: TyancDashboardRoles;
    permissions: TyancDashboardPermissions;
    files: TyancDashboardFiles;
    apps: TyancDashboardApps;
    alerts: TyancDashboardAlert[];
};
