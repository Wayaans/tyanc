export type ApprovalStatus = 'pending' | 'approved' | 'rejected';

export type ApprovalRequestRow = {
    id: string;
    action: string;
    action_label: string;
    status: ApprovalStatus;
    subject_name: string;
    subject_type: string | null;
    subject_id: string | null;
    request_note: string | null;
    review_note: string | null;
    requested_by_id: string | null;
    requested_by_name: string | null;
    reviewed_by_id: string | null;
    reviewed_by_name: string | null;
    payload: Record<string, unknown> | null;
    can_approve: boolean;
    can_reject: boolean;
    requested_at: string;
    reviewed_at: string | null;
};
