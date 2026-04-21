import { readonly, ref } from "vue";
import type {
  NotificationBroadcastPayload,
  NotificationItem,
  NotificationsPayload,
  ToastPayload,
} from "@/types";

const displayedFlashToastIds = ref<string[]>([]);
const notificationSummary = ref<NotificationsPayload>({
  unread_count: 0,
  recent: [],
});

function emptyNotificationsPayload(): NotificationsPayload {
  return {
    unread_count: 0,
    recent: [],
  };
}

function hasDisplayedFlashToast(toast: ToastPayload): boolean {
  return displayedFlashToastIds.value.includes(toast.id);
}

function rememberFlashToast(toast: ToastPayload): void {
  if (hasDisplayedFlashToast(toast)) {
    return;
  }

  displayedFlashToastIds.value = [
    ...displayedFlashToastIds.value,
    toast.id,
  ].slice(-20);
}

function replaceFromServer(payload: NotificationsPayload | null): void {
  notificationSummary.value = payload ?? emptyNotificationsPayload();
}

function pushLiveNotification(
  notification: NotificationBroadcastPayload
): NotificationItem | null {
  const normalizedNotification: NotificationItem = {
    id: notification.id,
    type: notification.type,
    kind: notification.kind,
    title: notification.title,
    body: notification.body,
    action_label: notification.action_label,
    action_url: notification.action_url,
    read: notification.read ?? false,
    read_at: notification.read_at ?? null,
    created_at: notification.created_at ?? new Date().toISOString(),
  };
  const existingNotificationIndex = notificationSummary.value.recent.findIndex(
    ({ id }) => id === normalizedNotification.id
  );

  if (existingNotificationIndex !== -1) {
    notificationSummary.value = {
      ...notificationSummary.value,
      recent: notificationSummary.value.recent.map((item, index) =>
        index === existingNotificationIndex ? normalizedNotification : item
      ),
    };

    return null;
  }

  notificationSummary.value = {
    unread_count: normalizedNotification.read
      ? notificationSummary.value.unread_count
      : notificationSummary.value.unread_count + 1,
    recent: [normalizedNotification, ...notificationSummary.value.recent].slice(
      0,
      8
    ),
  };

  return normalizedNotification;
}

function markAsRead(notificationId: string): void {
  const targetNotification = notificationSummary.value.recent.find(
    ({ id }) => id === notificationId
  );

  if (!targetNotification || targetNotification.read) {
    return;
  }

  notificationSummary.value = {
    unread_count: Math.max(0, notificationSummary.value.unread_count - 1),
    recent: notificationSummary.value.recent.map((item) =>
      item.id === notificationId
        ? {
            ...item,
            read: true,
            read_at: item.read_at ?? new Date().toISOString(),
          }
        : item
    ),
  };
}

function markAllAsRead(): void {
  notificationSummary.value = {
    unread_count: 0,
    recent: notificationSummary.value.recent.map((item) => ({
      ...item,
      read: true,
      read_at: item.read_at ?? new Date().toISOString(),
    })),
  };
}

export function useNotificationStore() {
  return {
    displayedFlashToastIds: readonly(displayedFlashToastIds),
    notifications: readonly(notificationSummary),
    hasDisplayedFlashToast,
    rememberFlashToast,
    replaceFromServer,
    pushLiveNotification,
    markAsRead,
    markAllAsRead,
  };
}
