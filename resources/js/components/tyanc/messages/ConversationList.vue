<script setup lang="ts">
import {
    Archive,
    ArchiveRestore,
    MessageSquare,
    SquarePen,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { useTranslations } from '@/lib/translations';
import type { ConversationRow } from '@/types';

const props = defineProps<{
    conversations: ConversationRow[];
    selectedId: string | null;
    canCreateConversation: boolean;
    viewMode: 'active' | 'archived';
    archivedConversationCount: number;
}>();

const emit = defineEmits<{
    select: [id: string];
    'new-conversation': [];
    'toggle-view': [];
}>();

const { __, locale } = useTranslations();

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

function leadParticipant(conversation: ConversationRow) {
    return conversation.participants[0] ?? null;
}
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden">
        <!-- Header — h-14 to match the MessageThread header height -->
        <div
            class="flex h-14 shrink-0 items-center gap-2 border-b border-border px-4"
        >
            <MessageSquare class="size-4 text-muted-foreground" />
            <h2 class="flex-1 text-sm font-semibold text-foreground">
                {{
                    props.viewMode === 'archived'
                        ? __('Archived')
                        : __('Messages')
                }}
            </h2>
            <Button
                variant="ghost"
                size="icon"
                class="size-7 text-muted-foreground"
                type="button"
                :disabled="!props.canCreateConversation"
                :aria-label="__('New conversation')"
                :title="
                    props.canCreateConversation
                        ? __('New conversation')
                        : __(
                              'You do not have permission to start a conversation.',
                          )
                "
                @click="emit('new-conversation')"
            >
                <SquarePen class="size-4" />
            </Button>
        </div>

        <div
            v-if="props.conversations.length === 0"
            class="flex flex-1 flex-col items-center justify-center gap-2 px-4 py-8 text-center"
        >
            <div
                class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
            >
                <MessageSquare class="size-5" />
            </div>
            <p class="text-sm font-medium text-foreground">
                {{
                    props.viewMode === 'archived'
                        ? __('No archived conversations.')
                        : __('No conversations yet.')
                }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{
                    props.viewMode === 'archived'
                        ? __('Conversations you archive will appear here.')
                        : __(
                              'Use the compose button above to start your first conversation.',
                          )
                }}
            </p>
        </div>

        <ul
            v-else
            class="flex-1 divide-y divide-border/50 overflow-y-auto"
            role="list"
        >
            <li
                v-for="conversation in props.conversations"
                :key="conversation.id"
                :aria-current="
                    props.selectedId === conversation.id ? 'true' : undefined
                "
            >
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                    :class="{
                        'bg-muted/40': props.selectedId === conversation.id,
                    }"
                    @click="emit('select', conversation.id)"
                >
                    <Avatar class="mt-0.5 shrink-0">
                        <AvatarImage
                            v-if="leadParticipant(conversation)?.avatar"
                            :src="
                                leadParticipant(conversation)?.avatar ??
                                undefined
                            "
                            :alt="conversation.title"
                        />
                        <AvatarFallback class="text-xs">
                            {{ getInitials(conversation.title) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class="truncate text-sm font-medium text-foreground"
                                :class="{
                                    'font-semibold':
                                        conversation.unread_count > 0,
                                }"
                            >
                                {{ conversation.title }}
                            </span>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ formatDate(conversation.last_message_at) }}
                            </span>
                        </div>

                        <div
                            class="mt-1 flex items-center justify-between gap-2"
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

                            <Badge
                                v-if="conversation.unread_count > 0"
                                class="h-5 min-w-5 shrink-0 rounded-full px-1 text-[10px] font-bold"
                            >
                                {{
                                    conversation.unread_count > 9
                                        ? '9+'
                                        : conversation.unread_count
                                }}
                            </Badge>
                        </div>
                    </div>
                </button>
            </li>
        </ul>

        <!-- Archive view toggle -->
        <div class="shrink-0 border-t border-border p-2">
            <Button
                variant="ghost"
                size="sm"
                class="w-full justify-start gap-2 text-xs text-muted-foreground hover:text-foreground"
                type="button"
                @click="emit('toggle-view')"
            >
                <ArchiveRestore
                    v-if="props.viewMode === 'archived'"
                    class="size-3.5"
                />
                <Archive v-else class="size-3.5" />

                <span v-if="props.viewMode === 'archived'">
                    {{ __('View active conversations') }}
                </span>
                <span v-else>
                    {{ __('Archived') }}
                    <span
                        v-if="props.archivedConversationCount > 0"
                        class="ml-1 tabular-nums"
                    >
                        ({{ props.archivedConversationCount }})
                    </span>
                </span>
            </Button>
        </div>
    </div>
</template>
