export type NotificationItem = {
  id: string;
  type: string;
  kind: string;
  title: string;
  body: string | null;
  action_label: string | null;
  action_url: string | null;
  read: boolean;
  read_at: string | null;
  created_at: string;
};

export type NotificationBroadcastPayload = {
  id: string;
  type: string;
  kind: string;
  title: string;
  body: string | null;
  action_label: string | null;
  action_url: string | null;
  read: boolean | null;
  read_at: string | null;
  created_at: string | null;
};

export type NotificationsPayload = {
  unread_count: number;
  recent: NotificationItem[];
};
