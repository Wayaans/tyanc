export type UserRow = {
    id: string;
    name: string;
    username: string | null;
    email: string;
    avatar: string | null;
    status: string;
    locale: string;
    timezone: string;
    roles: string[];
    permissions: string[];
    last_login_at: string | null;
    last_login_ip: string | null;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
    form?: UserFormData;
};

export type UserFormData = {
    id: string;
    name: string;
    username: string | null;
    email: string;
    avatar: string | null;
    status: string;
    timezone: string;
    locale: string;
    roles: string[];
    permissions: string[];
    email_verified_at: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    deleted_at: string | null;
    first_name: string | null;
    last_name: string | null;
    phone_number: string | null;
    date_of_birth: string | null;
    gender: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    company_name: string | null;
    job_title: string | null;
    bio: string | null;
    social_links: Record<string, string> | null;
    created_at: string;
    updated_at: string;
};

export type ActivityRow = {
    id: string;
    log_name: string | null;
    event: string | null;
    description: string;
    subject_type: string | null;
    subject_id: string | null;
    subject_name: string | null;
    causer_id: string | null;
    causer_name: string | null;
    properties: Record<string, unknown> | null;
    created_at: string;
};

export type NotificationItem = {
    id: string;
    type: string;
    kind: string;
    title: string;
    body: string | null;
    action_label: string | null;
    action_url: string | null;
    read: boolean;
    read_at: string | null;
    created_at: string;
};

export type NotificationsPayload = {
    unread_count: number;
    recent: NotificationItem[];
};

export type SelectOption = {
    value: string;
    label: string;
};

export type RoleOption = SelectOption & {
    level: number;
};
