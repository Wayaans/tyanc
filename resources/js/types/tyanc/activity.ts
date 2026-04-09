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
