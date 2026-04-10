export type ImportStatus =
    | 'pending_approval'
    | 'queued'
    | 'processing'
    | 'completed'
    | 'failed';

export type ImportRunRow = {
    id: string;
    type: string;
    status: ImportStatus;
    file_name: string;
    processed_rows: number;
    failure_message: string | null;
    created_by_id: string | null;
    created_by_name: string | null;
    approval_request_id: string | null;
    approval_status: 'pending' | 'approved' | 'rejected' | null;
    approval_reviewed_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
    updated_at: string;
};
