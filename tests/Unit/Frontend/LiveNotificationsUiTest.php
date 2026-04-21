<?php

declare(strict_types=1);

function normalizedLiveNotificationsSource(string $path): string
{
    return preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path($path))) ?? '';
}

it('subscribes to live user notifications and routes arrivals through the shared notification store', function (): void {
    $orchestrator = normalizedLiveNotificationsSource('js/components/NotificationOrchestrator.vue');
    $store = normalizedLiveNotificationsSource('js/composables/useNotificationStore.ts');
    $types = normalizedLiveNotificationsSource('js/types/tyanc/notifications.ts');

    expect($types)
        ->toContain('export type NotificationBroadcastPayload = {')
        ->toContain('read: boolean | null;')
        ->toContain('created_at: string | null;')
        ->and($store)
        ->toContain('function replaceFromServer(payload: NotificationsPayload | null): void')
        ->toContain('function pushLiveNotification( notification: NotificationBroadcastPayload, ): NotificationItem | null {')
        ->toContain('function markAsRead(notificationId: string): void')
        ->toContain('function markAllAsRead(): void')
        ->and($orchestrator)
        ->toContain('const authUserId = computed(() => page.props.auth.user?.id ?? null);')
        ->toContain('.private(`App.Models.User.${userId}`)')
        ->toContain('.notification(')
        ->toContain('(payload: NotificationBroadcastPayload) => {')
        ->toContain('const insertedNotification = pushLiveNotification(payload);')
        ->toContain('showLiveNotificationToast(insertedNotification);');
});

it('shows live message toasts through the shared orchestrator without merging messages into notifications store', function (): void {
    $orchestrator = normalizedLiveNotificationsSource('js/components/NotificationOrchestrator.vue');
    $notify = normalizedLiveNotificationsSource('js/lib/notify.ts');

    expect($notify)
        ->toContain('type NotifyAction = {')
        ->toContain('action?: NotifyAction;')
        ->toContain('action: options.action,')
        ->and($orchestrator)
        ->toContain('.private(`tyanc.users.${userId}.messages`)')
        ->toContain(".listen( '.message.sent', (payload: MessageSentEventPayload) => {")
        ->toContain("notify.info(__('New message'), {")
        ->toContain("label: __('Open conversation'),")
        ->toContain('onClick: () => {')
        ->toContain('notify.visit(')
        ->not->toContain('pushLiveNotification(payload.message');
});

it('keeps dropdown unread state in sync with optimistic store updates and server reconciliation', function (): void {
    $dropdown = normalizedLiveNotificationsSource('js/components/admin/NotificationDropdown.vue');

    expect($dropdown)
        ->toContain("import { useNotificationStore } from '@/composables/useNotificationStore';")
        ->toContain('const { notifications, markAllAsRead, markAsRead } = useNotificationStore();')
        ->toContain('markAllAsRead();')
        ->toContain('markAsRead(notification.id);')
        ->toContain("router.reload({ only: ['notifications'] });")
        ->toContain("only: ['notifications']");
});
