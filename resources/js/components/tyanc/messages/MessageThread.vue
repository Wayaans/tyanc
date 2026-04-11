<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';
import { useTranslations } from '@/lib/translations';
import type { ConversationRow, MessageRow } from '@/types';
import ComposeBox from './ComposeBox.vue';
import TypingIndicator from './TypingIndicator.vue';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type MessageGroup = {
    /** Stable v-for key – ID of the first message in the group. */
    key: string;
    senderId: string;
    senderName: string;
    senderAvatar: string | null;
    isMine: boolean;
    messages: MessageRow[];
    /** `created_at` of the last message – shown as the single group timestamp. */
    timestamp: string;
};

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** Max gap (ms) between two messages before starting a new group. */
const GROUP_THRESHOLD_MS = 5 * 60 * 1_000;

/** Pixels from the bottom edge within which we consider the user "at the bottom". */
const NEAR_BOTTOM_PX = 100;

// ---------------------------------------------------------------------------
// Props / emits
// ---------------------------------------------------------------------------

const props = defineProps<{
    conversation: ConversationRow;
    messages: MessageRow[];
    typingNames: string[];
    sending?: boolean;
    loading?: boolean;
    composeError?: string | null;
    canArchive?: boolean;
    canDelete?: boolean;
    isArchived?: boolean;
}>();

const emit = defineEmits<{
    send: [body: string];
    typing: [];
    archive: [];
    'delete-conversation': [];
}>();

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

const { __, locale } = useTranslations();
const page = usePage();
const threadRef = ref<HTMLElement | null>(null);
const deleteConfirmOpen = ref(false);

/**
 * Whether the scroll container is within NEAR_BOTTOM_PX of the bottom.
 * Starts true so the first batch of messages always scrolls into view.
 */
const isAtBottom = ref(true);

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

const timeFormatter = computed(() => {
    const tz = (page.props.auth as { user?: { timezone?: string } } | undefined)
        ?.user?.timezone;
    try {
        return new Intl.DateTimeFormat(locale.value, {
            hour: 'numeric',
            minute: '2-digit',
            ...(tz ? { timeZone: tz } : {}),
        });
    } catch {
        return new Intl.DateTimeFormat(locale.value, {
            hour: 'numeric',
            minute: '2-digit',
        });
    }
});

function formatTime(value: string): string {
    return timeFormatter.value.format(new Date(value));
}

// ---------------------------------------------------------------------------
// Message grouping
// ---------------------------------------------------------------------------

/**
 * Collapse consecutive messages from the same sender (within GROUP_THRESHOLD_MS)
 * into a single group so we show one avatar, one sender name, and one timestamp
 * per group instead of repeating them for every bubble.
 */
const messageGroups = computed((): MessageGroup[] => {
    const groups: MessageGroup[] = [];

    for (const message of props.messages) {
        const lastGroup = groups[groups.length - 1];
        const lastMessage = lastGroup?.messages[lastGroup.messages.length - 1];

        const sameSender = lastGroup?.senderId === message.sender_id;
        const closeInTime =
            lastMessage !== undefined
                ? new Date(message.created_at).getTime() -
                      new Date(lastMessage.created_at).getTime() <
                  GROUP_THRESHOLD_MS
                : false;

        if (lastGroup && sameSender && closeInTime) {
            lastGroup.messages.push(message);
            lastGroup.timestamp = message.created_at;
        } else {
            groups.push({
                key: message.id,
                senderId: message.sender_id,
                senderName: message.sender_name,
                senderAvatar: message.sender_avatar,
                isMine: message.is_mine,
                messages: [message],
                timestamp: message.created_at,
            });
        }
    }

    return groups;
});

// ---------------------------------------------------------------------------
// Bubble class helper
// ---------------------------------------------------------------------------

/**
 * Returns dynamic Tailwind classes for a single bubble inside a group.
 * The last bubble in a group gets the "tail" corner; earlier bubbles are
 * fully rounded so they stack cleanly.
 */
function bubbleClasses(
    group: MessageGroup,
    message: MessageRow,
    isLast: boolean,
): Record<string, boolean> {
    return {
        'bg-primary text-primary-foreground': group.isMine,
        'bg-muted text-foreground': !group.isMine,
        'rounded-br-sm': group.isMine && isLast,
        'rounded-bl-sm': !group.isMine && isLast,
        // Dim optimistic messages until the server confirms them.
        'opacity-70': message.id.startsWith('temp-'),
    };
}

// ---------------------------------------------------------------------------
// Scroll management
// ---------------------------------------------------------------------------

function checkIfAtBottom(): void {
    if (!threadRef.value) {
        return;
    }

    const { scrollTop, scrollHeight, clientHeight } = threadRef.value;
    isAtBottom.value =
        scrollTop + clientHeight >= scrollHeight - NEAR_BOTTOM_PX;
}

async function scrollToBottom(smooth = false): Promise<void> {
    await nextTick();

    if (!threadRef.value) {
        return;
    }

    // Mark as at-bottom optimistically so any watcher that fires right after
    // this call doesn't trigger a redundant second scroll.
    isAtBottom.value = true;

    threadRef.value.scrollTo({
        top: threadRef.value.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto',
    });
}

// Scroll when new messages arrive, but only when the user is already near
// the bottom OR the new message belongs to the current user (own sends always
// scroll so the user sees their message land).
watch(
    () => props.messages.length,
    (newLength, oldLength) => {
        // Ignore removals and the initial population (handled by onMounted).
        if (newLength <= (oldLength ?? 0)) {
            return;
        }

        const lastMessage = props.messages[newLength - 1];

        if (lastMessage?.is_mine || isAtBottom.value) {
            void scrollToBottom(true);
        }
    },
);

// Always scroll to the bottom of the new conversation when the active thread changes.
watch(
    () => props.conversation.id,
    () => {
        void scrollToBottom(false);
    },
);

onMounted(() => {
    void scrollToBottom(false);
});

// ---------------------------------------------------------------------------
// Delete confirmation
// ---------------------------------------------------------------------------

function confirmDelete() {
    deleteConfirmOpen.value = false;
    emit('delete-conversation');
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Thread header — h-14 to match ConversationList header height -->
        <div
            class="flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background px-4"
        >
            <Avatar class="size-8 shrink-0">
                <AvatarImage
                    v-if="props.conversation.participants[0]?.avatar"
                    :src="
                        props.conversation.participants[0].avatar ?? undefined
                    "
                    :alt="props.conversation.title"
                />
                <AvatarFallback class="text-xs">
                    {{ getInitials(props.conversation.title) }}
                </AvatarFallback>
            </Avatar>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-foreground">
                    {{ props.conversation.title }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ __('Messages update automatically') }}
                </p>
            </div>

            <!-- Archive / unarchive action -->
            <Button
                v-if="props.canArchive"
                variant="ghost"
                size="icon"
                class="size-8 shrink-0 text-muted-foreground hover:text-foreground"
                type="button"
                :title="
                    props.isArchived
                        ? __('Unarchive conversation')
                        : __('Archive conversation')
                "
                :aria-label="
                    props.isArchived
                        ? __('Unarchive conversation')
                        : __('Archive conversation')
                "
                @click="emit('archive')"
            >
                <ArchiveRestore v-if="props.isArchived" class="size-4" />
                <Archive v-else class="size-4" />
            </Button>

            <!-- Delete action — only when viewing archived conversations -->
            <Button
                v-if="props.canDelete && props.isArchived"
                variant="ghost"
                size="icon"
                class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                type="button"
                :title="__('Delete conversation')"
                :aria-label="__('Delete conversation')"
                @click="deleteConfirmOpen = true"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <!-- Message list -->
        <div
            ref="threadRef"
            class="min-h-0 flex-1 overflow-y-auto px-4 py-4"
            role="log"
            aria-live="polite"
            @scroll="checkIfAtBottom"
        >
            <!-- Loading skeleton -->
            <template v-if="props.loading">
                <div class="space-y-4">
                    <div
                        v-for="index in 4"
                        :key="index"
                        class="flex items-end gap-2"
                        :class="{ 'flex-row-reverse': index % 2 === 0 }"
                    >
                        <Skeleton class="size-8 shrink-0 rounded-full" />
                        <Skeleton
                            class="h-10 rounded-2xl"
                            :class="index % 2 === 0 ? 'w-1/2' : 'w-2/3'"
                        />
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <template v-else-if="props.messages.length === 0">
                <div
                    class="flex h-full flex-col items-center justify-center gap-1 text-center"
                >
                    <p class="text-sm font-medium text-foreground">
                        {{ __('No messages yet.') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ __('Say hello to start this conversation.') }}
                    </p>
                </div>
            </template>

            <!-- Grouped messages -->
            <div v-else class="flex min-h-full flex-col justify-end">
                <div class="space-y-3">
                    <div
                        v-for="group in messageGroups"
                        :key="group.key"
                        class="flex items-end gap-2"
                        :class="{ 'flex-row-reverse': group.isMine }"
                    >
                        <!-- Avatar shown once per group for other senders -->
                        <Avatar
                            v-if="!group.isMine"
                            class="mb-1 size-8 shrink-0 self-end"
                        >
                            <AvatarImage
                                v-if="group.senderAvatar"
                                :src="group.senderAvatar"
                                :alt="group.senderName"
                            />
                            <AvatarFallback class="text-xs">
                                {{ getInitials(group.senderName) }}
                            </AvatarFallback>
                        </Avatar>

                        <!-- Group body: sender name + stacked bubbles + single timestamp -->
                        <div class="max-w-[72%]">
                            <!-- Sender name (others only, once per group) -->
                            <p
                                v-if="!group.isMine"
                                class="mb-1 px-1 text-xs text-muted-foreground"
                            >
                                {{ group.senderName }}
                            </p>

                            <!-- Stacked bubbles -->
                            <div class="space-y-0.5">
                                <div
                                    v-for="(
                                        message, msgIndex
                                    ) in group.messages"
                                    :key="message.id"
                                    class="rounded-2xl px-3 py-2 text-sm leading-relaxed"
                                    :class="
                                        bubbleClasses(
                                            group,
                                            message,
                                            msgIndex ===
                                                group.messages.length - 1,
                                        )
                                    "
                                >
                                    <!-- whitespace-pre-wrap preserves newlines and bullet formatting -->
                                    <span
                                        class="break-words whitespace-pre-wrap"
                                        >{{ message.body }}</span
                                    >
                                </div>
                            </div>

                            <!-- Timestamp (once per group, below the last bubble) -->
                            <p
                                class="mt-1 px-1 text-xs text-muted-foreground"
                                :class="{ 'text-right': group.isMine }"
                            >
                                {{ formatTime(group.timestamp) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <TypingIndicator :names="props.typingNames" />

        <ComposeBox
            :sending="props.sending"
            :error="props.composeError"
            :disabled="props.isArchived"
            @send="(body) => emit('send', body)"
            @typing="emit('typing')"
        />

        <!-- Delete confirmation dialog -->
        <Dialog
            :open="deleteConfirmOpen"
            @update:open="deleteConfirmOpen = $event"
        >
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ __('Delete conversation') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            __(
                                'This will remove the archived conversation from your list. Other participants will keep their copy.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="deleteConfirmOpen = false"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button
                        variant="destructive"
                        type="button"
                        @click="confirmDelete"
                    >
                        {{ __('Delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
