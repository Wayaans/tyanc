export type SelectOption = {
    value: string;
    label: string;
};

export type RoleOption = SelectOption & {
    level: number;
};
