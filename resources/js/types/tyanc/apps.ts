export type AppPageData = {
    id: string;
    key: string;
    label: string;
    route_name: string | null;
    path: string | null;
    permission_name: string | null;
    sort_order: number;
    enabled: boolean;
    is_navigation: boolean;
    is_system: boolean;
};

export type AppData = {
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
    pages: AppPageData[];
};

export type AppRow = {
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
};
