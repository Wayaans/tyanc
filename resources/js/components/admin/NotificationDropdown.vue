<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { Bell, CheckCheck, ExternalLink } from "lucide-vue-next";
import { computed, ref } from "vue";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useNotificationStore } from "@/composables/useNotificationStore";
import { useTranslations } from "@/lib/translations";
import { index as activityLogRoute } from "@/routes/tyanc/activity-log";
import { markAllRead, update as markRead } from "@/routes/tyanc/notifications";
import type { NotificationItem } from "@/types";

const { notifications, markAllAsRead, markAsRead } = useNotificationStore();
const { __, locale } = useTranslations();

const unreadCount = computed(() => notifications.value.unread_count);
const recent = computed<NotificationItem[]>(() => notifications.value.recent);
const dateFormatter = computed(
  () =>
    new Intl.DateTimeFormat(locale.value, {
      dateStyle: "medium",
      timeStyle: "short",
    })
);

const markingAllRead = ref(false);

function reconcileNotifications(): void {
  router.reload({ only: ["notifications"] });
}

function handleMarkAllRead() {
  if (markingAllRead.value || unreadCount.value === 0) {
    return;
  }

  markingAllRead.value = true;
  markAllAsRead();

  router.patch(
    markAllRead.url(),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      only: ["notifications"],
      onError: () => {
        reconcileNotifications();
      },
      onFinish: () => {
        markingAllRead.value = false;
      },
    }
  );
}

function handleMarkRead(notification: NotificationItem) {
  if (notification.read) {
    return;
  }

  markAsRead(notification.id);

  router.patch(
    markRead.url({ notification: notification.id }),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      only: ["notifications"],
      onError: () => {
        reconcileNotifications();
      },
    }
  );
}

function handleAction(notification: NotificationItem) {
  handleMarkRead(notification);

  if (notification.action_url) {
    router.visit(notification.action_url);
  }
}

function formatDate(value: string): string {
  return dateFormatter.value.format(new Date(value));
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="icon" class="relative size-8">
        <Bell class="size-4" />
        <span
          v-if="unreadCount > 0"
          class="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
          :aria-label="__('Notifications')"
        >
          {{ unreadCount > 9 ? "9+" : unreadCount }}
        </span>
        <span class="sr-only">{{ __("Notifications") }}</span>
      </Button>
    </DropdownMenuTrigger>

    <DropdownMenuContent align="end" class="w-80 rounded-lg">
      <div class="flex items-center justify-between px-3 py-2">
        <DropdownMenuLabel class="p-0 text-sm font-semibold">
          {{ __("Notifications") }}
          <span
            v-if="unreadCount > 0"
            class="ml-1.5 inline-flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
          >
            {{ unreadCount }}
          </span>
        </DropdownMenuLabel>

        <Button
          v-if="unreadCount > 0"
          variant="ghost"
          size="sm"
          class="h-7 gap-1.5 px-2 text-xs"
          :disabled="markingAllRead"
          @click.stop="handleMarkAllRead"
        >
          <CheckCheck class="size-3.5" />
          {{ __("Mark all as read") }}
        </Button>
      </div>

      <DropdownMenuSeparator />

      <div
        v-if="recent.length === 0"
        class="flex flex-col items-center gap-2 px-4 py-8 text-center"
      >
        <div
          class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
          <Bell class="size-5" />
        </div>
        <p class="text-sm font-medium text-foreground">
          {{ __("You're all caught up.") }}
        </p>
        <p class="text-xs text-muted-foreground">
          {{ __("No unread notifications.") }}
        </p>
      </div>

      <ul v-else class="max-h-80 divide-y divide-border/50 overflow-y-auto">
        <li
          v-for="notification in recent"
          :key="notification.id"
          :class="[
            'group relative px-3 py-3 transition-colors',
            notification.read
              ? 'bg-background'
              : 'bg-primary/5 hover:bg-primary/8',
          ]"
        >
          <span
            v-if="!notification.read"
            class="absolute top-1/2 left-2 size-1.5 -translate-y-1/2 rounded-full bg-primary"
            aria-hidden="true"
          />

          <div :class="['space-y-0.5', !notification.read ? 'pl-2' : '']">
            <p class="text-sm leading-none font-medium text-foreground">
              {{ notification.title }}
            </p>
            <p
              v-if="notification.body"
              class="line-clamp-2 text-xs text-muted-foreground"
            >
              {{ notification.body }}
            </p>
            <div class="flex items-center justify-between gap-2 pt-1">
              <button
                v-if="notification.action_label && notification.action_url"
                type="button"
                class="text-xs font-medium text-primary hover:underline"
                @click="handleAction(notification)"
              >
                {{ notification.action_label }}
              </button>
              <button
                v-else-if="!notification.read"
                type="button"
                class="text-xs text-muted-foreground hover:text-foreground"
                @click="handleMarkRead(notification)"
              >
                {{ __("Mark as read") }}
              </button>
              <span
                class="ml-auto text-xs whitespace-nowrap text-muted-foreground"
              >
                {{ formatDate(notification.created_at) }}
              </span>
            </div>
          </div>
        </li>
      </ul>

      <DropdownMenuSeparator />

      <div class="px-3 py-2">
        <Button
          variant="ghost"
          size="sm"
          class="w-full justify-start gap-1.5 text-xs text-muted-foreground"
          @click="router.visit(activityLogRoute.url())"
        >
          <ExternalLink class="size-3.5" />
          {{ __("View activity log") }}
        </Button>
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
