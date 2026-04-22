<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, watch } from "vue";
import { useNotificationStore } from "@/composables/useNotificationStore";
import { getEcho } from "@/lib/echo";
import { notify, reverbNotificationsEnabled } from "@/lib/notify";
import { useTranslations } from "@/lib/translations";
import { index as messagesIndex } from "@/routes/tyanc/messages";
import type {
  FlashProps,
  MessageSentEventPayload,
  NotificationBroadcastPayload,
  NotificationItem,
  NotificationsPayload,
  ToastPayload,
} from "@/types";

const page = usePage();
const { __ } = useTranslations();
const {
  hasDisplayedFlashToast,
  rememberFlashToast,
  replaceFromServer,
  pushLiveNotification,
} = useNotificationStore();

const authUserId = computed(() => page.props.auth.user?.id ?? null);
const reverbNotificationsEnabledOnPage = computed<boolean>(
  () =>
    reverbNotificationsEnabled() &&
    page.props.notificationSettings.reverb_enabled
);
const flashToast = computed<ToastPayload | null>(() => {
  const flash = (page.props.flash as FlashProps | undefined) ?? null;

  return flash?.toast ?? null;
});
const serverNotifications = computed<NotificationsPayload | null>(
  () => (page.props.notifications as NotificationsPayload | null) ?? null
);

let stopWatchingFlashToast: (() => void) | null = null;
let stopWatchingNotifications: (() => void) | null = null;
let stopWatchingAuthUser: (() => void) | null = null;
let activeNotificationChannel: string | null = null;
let activeMessageChannel: string | null = null;

function showLiveNotificationToast(notification: NotificationItem): void {
  switch (notification.kind) {
    case "approval-approved":
      notify.success(notification.title, {
        description: notification.body,
      });
      return;
    case "approval-rejected":
      notify.error(notification.title, {
        description: notification.body,
      });
      return;
    case "approval-cancelled":
    case "approval-escalated":
      notify.warning(notification.title, {
        description: notification.body,
      });
      return;
    default:
      notify.info(notification.title, { description: notification.body });
  }
}

function leaveActiveNotificationChannel(): void {
  if (!activeNotificationChannel) {
    return;
  }

  getEcho()?.leave(activeNotificationChannel);
  activeNotificationChannel = null;
}

function leaveActiveMessageChannel(): void {
  if (!activeMessageChannel) {
    return;
  }

  getEcho()?.leave(activeMessageChannel);
  activeMessageChannel = null;
}

function showLiveMessageToast(payload: MessageSentEventPayload): void {
  notify.info(__("New message"), {
    description:
      payload.conversation.last_sender_name &&
      payload.conversation.last_message_preview
        ? `${payload.conversation.last_sender_name}: ${payload.conversation.last_message_preview}`
        : (payload.conversation.last_message_preview ?? payload.message.body),
    action: {
      label: __("Open conversation"),
      onClick: () => {
        notify.visit(
          messagesIndex.url({
            query: { conversation: payload.conversation.id },
          })
        );
      },
    },
  });
}

onMounted(() => {
  stopWatchingNotifications = watch(
    serverNotifications,
    (payload) => {
      replaceFromServer(payload);
    },
    { immediate: true }
  );

  stopWatchingFlashToast = watch(
    flashToast,
    (toastPayload) => {
      if (!toastPayload || hasDisplayedFlashToast(toastPayload)) {
        return;
      }

      rememberFlashToast(toastPayload);
      notify.show(toastPayload);
    },
    { immediate: true }
  );

  stopWatchingAuthUser = watch(
    [authUserId, reverbNotificationsEnabledOnPage],
    ([userId, reverbEnabled]) => {
      leaveActiveNotificationChannel();
      leaveActiveMessageChannel();

      if (!userId) {
        return;
      }

      const echo = getEcho();

      if (!echo) {
        return;
      }

      activeMessageChannel = `tyanc.users.${userId}.messages`;

      if (reverbEnabled) {
        activeNotificationChannel = `App.Models.User.${userId}`;

        echo
          .private(`App.Models.User.${userId}`)
          .notification((payload: NotificationBroadcastPayload) => {
            const insertedNotification = pushLiveNotification(payload);

            if (insertedNotification === null) {
              return;
            }

            showLiveNotificationToast(insertedNotification);
          });
      }

      echo
        .private(`tyanc.users.${userId}.messages`)
        .listen(".message.sent", (payload: MessageSentEventPayload) => {
          if (payload.message.sender_id === userId) {
            return;
          }

          showLiveMessageToast(payload);
        });
    },
    { immediate: true }
  );
});

onUnmounted(() => {
  stopWatchingFlashToast?.();
  stopWatchingNotifications?.();
  stopWatchingAuthUser?.();
  leaveActiveNotificationChannel();
  leaveActiveMessageChannel();
});
</script>

<template />
