<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { MessageSquareMore } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { getEcho } from '@/lib/echo';
import { useTranslations } from '@/lib/translations';
import { index as messagesRoute } from '@/routes/tyanc/messages';
import type { ConversationRow, MessagesShellPayload } from '@/types';

const page = usePage();
const { __, locale } = useTranslations();

const messagesPayload = computed<MessagesShellPayload>(
    () =>
        (page.props.messages as MessagesShellPayload | undefined) ?? {
            unread_count: 0,
            recent: [],
        },
);

const unreadCount = ref<number>(
    (page.props.messagesUnreadCount as number | undefined) ?? 0,
);
const recent = ref<ConversationRow[]>(messagesPayload.value.recent);

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            month: 'short',
            day: 'numeric',
        }),
);

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return dateFormatter.value.format(new Date(value));
}

// Keep unread count in sync with server-driven page prop updates (e.g. after
// Inertia navigations that carry a fresh messagesUnreadCount prop).
watch(
    () => page.props.messagesUnreadCount,
    (value) => {
        unreadCount.value = (value as number | undefined) ?? 0;
    },
);

watch(
    messagesPayload,
    (value) => {
        recent.value = value.recent;
    },
    { immediate: true },
);

// Realtime: increment count on new incoming messages via private user channel.
const authUserId = computed<string>(
    () =>
        (page.props.auth as { user?: { id: string } } | undefined)?.user?.id ??
        '',
);

const shellMessagesChannel = computed(() =>
    authUserId.value === '' ? null : `tyanc.users.${authUserId.value}.messages`,
);

// The messages page dispatches this event when it marks messages read so the
// badge stays in sync without a full navigation.
function handleUnreadCountUpdate(event: Event) {
    const detail = (
        event as CustomEvent<{
            unreadCount: number;
            recent?: ConversationRow[];
        }>
    ).detail;

    if (typeof detail?.unreadCount === 'number') {
        unreadCount.value = detail.unreadCount;
    }

    if (Array.isArray(detail?.recent)) {
        recent.value = detail.recent;
    }
}

watch(
    shellMessagesChannel,
    (channelName, previousChannelName) => {
        const echo = getEcho();

        if (!echo) {
            return;
        }

        if (previousChannelName) {
            echo.leave(previousChannelName);
        }

        if (!channelName) {
            return;
        }

        echo.private(channelName).listen(
            '.message.sent',
            (payload: {
                message?: {
                    sender_id?: string;
                    body?: string;
                    created_at?: string;
                };
                conversation?: {
                    id: string;
                    last_message_preview?: string | null;
                    last_message_at?: string | null;
                    last_sender_name?: string | null;
                };
            }) => {
                if (payload.message?.sender_id === authUserId.value) {
                    return;
                }

                unreadCount.value += 1;

                if (!payload.conversation) {
                    return;
                }

                const nextConversation = recent.value.find(
                    (conversation) =>
                        conversation.id === payload.conversation?.id,
                );

                if (nextConversation) {
                    nextConversation.last_message_preview =
                        payload.conversation.last_message_preview ??
                        payload.message?.body ??
                        nextConversation.last_message_preview;
                    nextConversation.last_message_at =
                        payload.conversation.last_message_at ??
                        payload.message?.created_at ??
                        nextConversation.last_message_at;
                    nextConversation.last_sender_name =
                        payload.conversation.last_sender_name ??
                        nextConversation.last_sender_name;
                    nextConversation.unread_count += 1;

                    recent.value = [
                        nextConversation,
                        ...recent.value.filter(
                            (conversation) =>
                                conversation.id !== nextConversation.id,
                        ),
                    ].slice(0, 6);

                    return;
                }

                recent.value = [
                    {
                        id: payload.conversation.id,
                        title: __('Conversation'),
                        subject: null,
                        participant_count: 0,
                        message_count: 0,
                        unread_count: 1,
                        last_message_preview:
                            payload.conversation.last_message_preview ??
                            payload.message?.body ??
                            null,
                        last_message_at:
                            payload.conversation.last_message_at ??
                            payload.message?.created_at ??
                            null,
                        last_sender_name:
                            payload.conversation.last_sender_name ?? null,
                        participants: [],
                        messages: [],
                        created_at:
                            payload.message?.created_at ??
                            new Date().toISOString(),
                        updated_at:
                            payload.message?.created_at ??
                            new Date().toISOString(),
                    },
                    ...recent.value,
                ].slice(0, 6);
            },
        );
    },
    { immediate: true },
);

if (typeof window !== 'undefined') {
    window.addEventListener(
        'messages-unread-count:update',
        handleUnreadCountUpdate,
    );
}

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener(
            'messages-unread-count:update',
            handleUnreadCountUpdate,
        );
    }

    const echo = getEcho();

    if (echo && shellMessagesChannel.value) {
        echo.leave(shellMessagesChannel.value);
    }
});

function openConversation(conversation: ConversationRow) {
    router.visit(
        messagesRoute.url({ query: { conversation: conversation.id } }),
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative size-8">
                <MessageSquareMore class="size-4" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                    aria-hidden="true"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
                <span class="sr-only">{{ __('Messages') }}</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80 rounded-lg">
            <div class="flex items-center justify-between px-3 py-2">
                <DropdownMenuLabel class="p-0 text-sm font-semibold">
                    {{ __('Messages') }}
                    <span
                        v-if="unreadCount > 0"
                        class="ml-1.5 inline-flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                        aria-hidden="true"
                    >
                        {{ unreadCount > 9 ? '9+' : unreadCount }}
                    </span>
                </DropdownMenuLabel>
            </div>

            <DropdownMenuSeparator />

            <!-- Empty state -->
            <div
                v-if="recent.length === 0"
                class="flex flex-col items-center gap-2 px-4 py-8 text-center"
            >
                <div
                    class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
                >
                    <MessageSquareMore class="size-5" />
                </div>
                <p class="text-sm font-medium text-foreground">
                    {{ __('No messages yet.') }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ __('Your recent conversations will appear here.') }}
                </p>
            </div>

            <!-- Recent conversations list -->
            <ul
                v-else
                class="max-h-80 divide-y divide-border/50 overflow-y-auto"
                role="list"
            >
                <li
                    v-for="conversation in recent"
                    :key="conversation.id"
                    :class="{
                        'bg-primary/5': conversation.unread_count > 0,
                    }"
                >
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 px-3 py-3 text-left transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                        @click="openConversation(conversation)"
                    >
                        <Avatar class="mt-0.5 size-8 shrink-0">
                            <AvatarImage
                                v-if="conversation.participants[0]?.avatar"
                                :src="
                                    conversation.participants[0].avatar ??
                                    undefined
                                "
                                :alt="conversation.title"
                            />
                            <AvatarFallback class="text-xs">
                                {{ getInitials(conversation.title) }}
                            </AvatarFallback>
                        </Avatar>

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="truncate text-sm text-foreground"
                                    :class="{
                                        'font-semibold':
                                            conversation.unread_count > 0,
                                        'font-medium':
                                            conversation.unread_count === 0,
                                    }"
                                >
                                    {{ conversation.title }}
                                </span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                >
                                    {{
                                        formatDate(conversation.last_message_at)
                                    }}
                                </span>
                            </div>

                            <div
                                class="mt-0.5 flex items-center justify-between gap-2"
                            >
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                    :class="{
                                        'font-medium text-foreground':
                                            conversation.unread_count > 0,
                                    }"
                                >
                                    {{
                                        conversation.last_message_preview ??
                                        __('No messages yet.')
                                    }}
                                </p>
                                <span
                                    v-if="conversation.unread_count > 0"
                                    class="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                                    aria-hidden="true"
                                >
                                    {{
                                        conversation.unread_count > 9
                                            ? '9+'
                                            : conversation.unread_count
                                    }}
                                </span>
                            </div>
                        </div>
                    </button>
                </li>
            </ul>

            <DropdownMenuSeparator />

            <div class="px-3 py-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-full justify-start gap-1.5 text-xs text-muted-foreground"
                    @click="router.visit(messagesRoute.url())"
                >
                    <MessageSquareMore class="size-3.5" />
                    {{ __('Open messages') }}
                </Button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
