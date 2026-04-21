<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, watch } from "vue";
import { useNotificationStore } from "@/composables/useNotificationStore";
import { getEcho } from "@/lib/echo";
import { notify } from "@/lib/notify";
import type {
  FlashProps,
  NotificationBroadcastPayload,
  NotificationItem,
  NotificationsPayload,
  ToastPayload,
} from "@/types";

const page = usePage();
const {
  hasDisplayedFlashToast,
  rememberFlashToast,
  replaceFromServer,
  pushLiveNotification,
} = useNotificationStore();

const authUserId = computed(() => page.props.auth.user?.id ?? null);
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
    authUserId,
    (userId) => {
      leaveActiveNotificationChannel();

      if (!userId) {
        return;
      }

      const echo = getEcho();

      if (!echo) {
        return;
      }

      activeNotificationChannel = `App.Models.User.${userId}`;

      echo
        .private(`App.Models.User.${authUserId.value}`)
        .notification((payload: NotificationBroadcastPayload) => {
          const insertedNotification = pushLiveNotification(payload);

          if (insertedNotification === null) {
            return;
          }

          showLiveNotificationToast(insertedNotification);
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
});
</script>

<template />
