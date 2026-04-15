export type MediaFileRow = {
    id: number;
    name: string;
    file_name: string;
    relative_path: string;
    directory_path: string;
    extension: string | null;
    mime_type: string;
    mime_group: string;
    size_bytes: number;
    size_human: string;
    is_image: boolean;
    is_previewable: boolean;
    is_public: boolean;
    is_deletable: boolean;
    preview_url: string | null;
    url: string;
    download_url: string;
    disk: string;
    storage_path: string;
    source: string;
    source_label: string;
    app_key: string;
    app_label: string;
    resource_key: string;
    folder_path: string;
    folder_label: string;
    collection_name: string | null;
    media_id: number | null;
    subject_type: string | null;
    subject_id: string | null;
    subject_label: string | null;
    uploaded_by_id: string | null;
    uploaded_by_name: string | null;
    custom_properties: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
};

export type ManagedFileRow = MediaFileRow;

export type FileExplorerFolder = {
    path: string;
    label: string;
    total_files: number;
};

export type FileExplorerApp = {
    key: string;
    label: string;
    total_files: number;
    folders: FileExplorerFolder[];
};

export type FileExplorer = {
    total_files: number;
    total_size_bytes: number;
    total_size_human: string;
    app_count: number;
    folder_count: number;
    media_files: number;
    public_files: number;
    apps: FileExplorerApp[];
};

export type FileExplorerAbilities = {
    download: boolean;
    delete: boolean;
};
