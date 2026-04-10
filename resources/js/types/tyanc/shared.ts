export type SelectOption = {
    value: string;
    label: string;
};

export type RoleOption = SelectOption & {
    level: number;
    permission_count: number;
    is_reserved: boolean;
    permissions: string[];
};

export type PermissionOption = SelectOption & {
    app: string | null;
    resource: string | null;
    action: string | null;
    is_reserved: boolean;
};

/** Hierarchical permission options used for the role permission assignment flow. */
export type PermissionOptions = {
    apps: SelectOption[];
    resources: Record<string, SelectOption[]>;
    actions: Record<
        string,
        Record<string, Array<SelectOption & { permission: string }>>
    >;
};
