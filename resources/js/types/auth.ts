export type UserProfile = {
    id: string;
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

export type User = {
    id: string;
    name: string;
    username: string;
    email: string;
    avatar: string | null;
    status: string;
    timezone: string;
    locale: string;
    email_verified_at: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string;
    updated_at: string;
    profile: UserProfile | null;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
