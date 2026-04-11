export type User = {
    id: string;
    name: string;
    username: string;
    email: string;
    avatar: string | null;
    status: string;
    timezone: string;
    locale: string;
    is_reserved: boolean;
    reserved_key: string | null;
    email_verified_at: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string;
    updated_at: string;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
