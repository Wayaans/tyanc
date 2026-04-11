export type ConversationParticipant = {
    id: string;
    name: string;
    username: string;
    avatar: string | null;
};

export type MessageRow = {
    id: string;
    conversation_id: string;
    sender_id: string;
    sender_name: string;
    sender_avatar: string | null;
    body: string;
    is_mine: boolean;
    created_at: string;
};

export type ConversationRow = {
    id: string;
    title: string;
    subject: string | null;
    participant_count: number;
    message_count: number;
    unread_count: number;
    last_message_preview: string | null;
    last_message_at: string | null;
    last_sender_name: string | null;
    participants: ConversationParticipant[];
    messages: MessageRow[];
    created_at: string;
    updated_at: string;
};

export type MessagesShellPayload = {
    unread_count: number;
    recent: ConversationRow[];
};

export type MessagesPageProps = {
    conversations: ConversationRow[];
    contacts: ConversationParticipant[];
    abilities?: {
        createConversation: boolean;
        archiveConversation: boolean;
        deleteConversation: boolean;
    };
    selectedConversation: ConversationRow | null;
    selectedConversationId: string | null;
    unreadCount: number;
    viewMode: 'active' | 'archived';
    archivedConversationCount: number;
};

export type MessageSentEventPayload = {
    conversation: {
        id: string;
        last_message_preview: string | null;
        last_message_at: string | null;
        last_sender_name: string | null;
    };
    message: MessageRow;
};
