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
    // Phase 3 additions
    rule_id: string | null;
    pending_assignee_names: string[];
    current_step_label: string | null;
    current_step_order: number | null;
    can_reassign: boolean;
    is_reassigned: boolean;
    is_escalated: boolean;
    last_reassigned_at: string | null;
    last_reminded_at: string | null;
    escalated_at: string | null;
};

export type ApprovalReportRow = {
    id: string;
    action_label: string;
    subject_name: string;
    status: ApprovalStatus;
    requested_by_name: string | null;
    current_step_label: string | null;
    current_step_order: number | null;
    current_assignee_names: string[];
    is_overdue: boolean;
    is_reassigned: boolean;
    is_escalated: boolean;
    requested_at: string;
    reviewed_at: string | null;
    escalated_at: string | null;
    last_reassigned_at: string | null;
    app_label: string | null;
    rule_id: string | null;
};

export type ApprovalReportSummary = {
    total: number;
    pending: number;
    in_review: number;
    approved: number;
    rejected: number;
    cancelled: number;
    overdue: number;
    escalated: number;
    reassigned: number;
};
