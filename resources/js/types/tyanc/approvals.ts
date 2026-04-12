export type ApprovalStatus =
    | 'draft'
    | 'pending'
    | 'in_review'
    | 'approved'
    | 'rejected'
    | 'cancelled'
    | 'expired'
    | 'superseded';

export type ApprovalRequestRow = {
    id: string;
    app_key: string | null;
    resource_key: string | null;
    action_key: string | null;
    action: string;
    action_label: string;
    status: ApprovalStatus;
    subject_name: string;
    subject_type: string | null;
    subject_id: string | null;
    subject_snapshot: Record<string, unknown> | null;
    request_note: string | null;
    review_note: string | null;
    requested_by_id: string | null;
    requested_by_name: string | null;
    reviewed_by_id: string | null;
    reviewed_by_name: string | null;
    payload: Record<string, unknown> | null;
    before_payload: Record<string, unknown> | null;
    after_payload: Record<string, unknown> | null;
    impact_summary: string | null;
    can_approve: boolean;
    can_reject: boolean;
    can_cancel: boolean;
    is_assigned_to_actor: boolean;
    requested_at: string;
    reviewed_at: string | null;
    cancelled_at: string | null;
};
