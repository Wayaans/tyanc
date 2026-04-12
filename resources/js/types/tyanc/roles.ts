import type { GovernedActionState } from '@/types/cumpu';

export type RoleData = {
    id: number;
    name: string;
    guard_name: string;
    level: number;
    permission_count: number;
    user_count: number;
    is_reserved: boolean;
    is_delete_protected: boolean;
    permissions: string[];
    created_at: string;
    updated_at: string;
    update_approval_state?: GovernedActionState | null;
};

export type RoleRow = RoleData;
