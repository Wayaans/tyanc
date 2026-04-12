export type ApprovalRuleWorkflowType = 'single';

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
    step_role_id: number | null;
    step_role_name: string | null;
    step_label: string | null;
};

export type ApprovalRuleFormPayload = {
    app_key: string;
    resource_key: string;
    action_key: string;
    permission_name: string;
    workflow_type: ApprovalRuleWorkflowType;
    step_role_id: number | null;
    enabled: boolean;
};

export type CumpuDashboardSummary = {
    pending_inbox_count: number;
    my_request_count: number;
    enabled_rule_count: number;
};

export type CumpuDashboardAbilities = {
    viewInbox: boolean;
    viewMyRequests: boolean;
    manageRules: boolean;
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
