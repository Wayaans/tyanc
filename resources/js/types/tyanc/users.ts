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
    is_reserved: boolean;
    is_delete_protected: boolean;
    reserved_key: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
    form?: UserFormData;
};

export type UserUpdateDraftState =
    | 'draft'
    | 'submitted_for_approval'
    | 'approved_for_commit'
    | 'rejected_for_revision'
    | 'committed';

export type UserUpdateDraft = {
    id: string;
    user_id: string;
    created_by_id: string;
    revision: number;
    changed_fields: string[];
    /** Serialized form field values stored in the draft */
    form_values: Record<string, unknown> | null;
    has_password_change: boolean;
    state: UserUpdateDraftState;
    has_committable_draft: boolean;
    has_stale_subject_revision: boolean;
    relevant_request:
        | import('@/types/cumpu').GovernedActionRelevantRequest
        | null;
    committed_at: string | null;
    updated_at: string;
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
    is_reserved: boolean;
    is_delete_protected: boolean;
    reserved_key: string | null;
    email_verified_at: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
};
