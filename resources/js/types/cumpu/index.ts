export type ApprovalRuleWorkflowType = 'single' | 'multi';

export type ApprovalRuleStep = {
    id?: string;
    label: string;
    role_id: number | null;
    role_name: string | null;
    order: number;
};

export type ApprovalRuleStepFormData = {
    label: string;
    role_id: number | null;
    order: number;
};

export type ApprovalRule = {
    id: string;
    app_key: string;
    app_label: string;
    resource_key: string;
    resource_label: string;
    action_key: string;
    action_label: string;
    permission_name: string;
    enabled: boolean;
    workflow_type: ApprovalRuleWorkflowType;
    grant_validity_minutes: number;
    // Legacy single-step convenience fields (may be null for multi-step)
    step_role_id: number | null;
    step_role_name: string | null;
    step_label: string | null;
    // Multi-step
    steps: ApprovalRuleStep[];
    reminder_after_minutes: number | null;
    escalation_after_minutes: number | null;
};

export type ApprovalRuleFormPayload = {
    app_key: string;
    resource_key: string;
    action_key: string;
    permission_name: string;
    workflow_type: ApprovalRuleWorkflowType;
    grant_validity_minutes: number | null;
    steps: ApprovalRuleStepFormData[];
    reminder_after_minutes: number | null;
    escalation_after_minutes: number | null;
    enabled: boolean;
};

export type CumpuDashboardSummary = {
    pending_inbox_count: number;
    my_request_count: number;
    ready_to_retry_count: number;
    consumed_count: number;
    expired_count: number;
    enabled_rule_count: number;
    all_pending_count: number;
    overdue_count: number;
};

export type CumpuDashboardAbilities = {
    viewInbox: boolean;
    viewMyRequests: boolean;
    manageRules: boolean;
    viewAll: boolean;
    viewReports: boolean;
};

export type RoleOption = {
    value: number;
    label: string;
    level: number;
};

export type ApprovalAssignmentRow = {
    id: string;
    status: string;
    assigned_to_id: string | null;
    assigned_to_name: string | null;
    completed_by_id: string | null;
    completed_by_name: string | null;
    step_label: string | null;
    step_order: number | null;
    role_name: string | null;
    assigned_at: string;
    completed_at: string | null;
};

export type ReassignOption = {
    assignment_id: string;
    assigned_to_id: string | null;
    assigned_to_name: string | null;
    step_label: string | null;
    step_order: number | null;
    eligible_assignees: Array<{ value: string; label: string }>;
};

export type GovernedActionRelevantRequest = {
    id: string;
    status: import('@/types').ApprovalStatus;
    action_label: string;
    requested_by_name: string | null;
    current_step_label: string | null;
    requested_at: string;
    reviewed_at: string | null;
    expires_at: string | null;
    consumed_at: string | null;
    consumed_by_name: string | null;
    is_grant_usable: boolean;
    is_grant_expired: boolean;
    detail_url: string | null;
};

export type GovernedActionState = {
    action_key: string;
    permission_name: string;
    approval_enabled: boolean;
    approval_required: boolean;
    bypasses_for_actor: boolean;
    has_usable_grant: boolean;
    has_blocking_request: boolean;
    relevant_request: GovernedActionRelevantRequest | null;
};

export type ApprovalContextRequest = {
    id: string;
    status: import('@/types').ApprovalStatus;
    action_label: string;
    requested_by_name: string | null;
    current_step_label: string | null;
    requested_at: string;
    reviewed_at: string | null;
    expires_at: string | null;
    consumed_at: string | null;
    consumed_by_name: string | null;
    is_grant_usable: boolean;
    is_grant_expired: boolean;
    detail_url: string | null;
};

export type ApprovalContext = {
    scope_label: string;
    pending_count: number;
    has_pending_requests: boolean;
    can_view_requests: boolean;
    latest_pending_request: ApprovalContextRequest | null;
    history: ApprovalContextRequest[];
    governed_actions?: Record<string, GovernedActionState>;
};
