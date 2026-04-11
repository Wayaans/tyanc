<script setup lang="ts">
import { Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { getInitials } from '@/composables/useInitials';
import { currentSocketId } from '@/lib/echo';
import { useTranslations } from '@/lib/translations';
import { create as createConversation } from '@/routes/tyanc/messages';
import type { ConversationParticipant } from '@/types';

const props = withDefaults(
    defineProps<{
        open: boolean;
        contacts?: ConversationParticipant[];
        canCreateConversation: boolean;
    }>(),
    {
        contacts: () => [],
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    created: [conversationId: string];
}>();

const { __ } = useTranslations();

const search = ref('');
const selectedIds = ref<string[]>([]);
const subject = ref('');
const body = ref('');
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const filteredContacts = computed(() => {
    const q = search.value.trim().toLowerCase();

    if (!q) {
        return props.contacts;
    }

    return props.contacts.filter(
        (contact) =>
            contact.name.toLowerCase().includes(q) ||
            contact.username.toLowerCase().includes(q),
    );
});

const canSubmit = computed(
    () =>
        props.canCreateConversation &&
        selectedIds.value.length > 0 &&
        body.value.trim().length > 0 &&
        !processing.value,
);

// Reset form state when dialog opens.
watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        search.value = '';
        selectedIds.value = [];
        subject.value = '';
        body.value = '';
        errors.value = {};
    },
);

function csrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function requestHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        ...(currentSocketId()
            ? { 'X-Socket-ID': currentSocketId() ?? '' }
            : {}),
    };
}

function setContactSelection(id: string, checked: boolean | 'indeterminate') {
    if (checked === 'indeterminate') {
        return;
    }

    if (checked) {
        selectedIds.value = Array.from(new Set([...selectedIds.value, id]));

        return;
    }

    selectedIds.value = selectedIds.value.filter((value) => value !== id);
}

function toggleContact(id: string) {
    setContactSelection(id, !selectedIds.value.includes(id));
}

function isSelected(id: string): boolean {
    return selectedIds.value.includes(id);
}

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    try {
        const response = await fetch(createConversation.url(), {
            method: 'POST',
            headers: requestHeaders(),
            body: JSON.stringify({
                participant_ids: selectedIds.value,
                subject: subject.value.trim() || null,
                message: body.value.trim(),
            }),
        });

        const payload = (await response.json().catch(() => null)) as {
            conversation?: { id: string };
            id?: string;
            errors?: Record<string, string[]>;
            message?: string;
        } | null;

        if (!response.ok) {
            if (response.status === 422 && payload?.errors) {
                const flat: Record<string, string> = {};

                for (const [field, messages] of Object.entries(
                    payload.errors,
                )) {
                    flat[field] = messages[0] ?? '';
                }

                errors.value = {
                    participant_ids:
                        flat.participant_ids ?? flat['participant_ids.0'] ?? '',
                    subject: flat.subject ?? '',
                    message: flat.message ?? '',
                };
            } else {
                errors.value = {
                    message:
                        payload?.message ??
                        __(
                            'Unable to start the conversation. Please try again.',
                        ),
                };
            }

            return;
        }

        const conversationId = payload?.conversation?.id ?? payload?.id ?? null;

        if (!conversationId) {
            errors.value = {
                message: __('Unexpected response from server. Please refresh.'),
            };

            return;
        }

        emit('update:open', false);
        emit('created', conversationId);
    } catch {
        errors.value = {
            message: __('Unable to start the conversation. Please try again.'),
        };
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="(v) => emit('update:open', v)">
        <DialogScrollContent class="flex max-h-[90dvh] flex-col sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ __('New conversation') }}</DialogTitle>
                <DialogDescription>
                    {{
                        __(
                            'Choose one or more people to message, add an optional subject, and write your first message.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto py-1 pr-1"
            >
                <!-- Contact search + list -->
                <div class="flex flex-col gap-2">
                    <Label>{{ __('Recipients') }}</Label>

                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="search"
                            :placeholder="__('Search contacts…')"
                            :disabled="!props.canCreateConversation"
                            class="pl-9"
                            autocomplete="off"
                        />
                    </div>

                    <div
                        v-if="!props.canCreateConversation"
                        class="flex flex-col items-center gap-2 rounded-md border border-dashed border-border px-4 py-6 text-center"
                    >
                        <Users class="size-8 text-muted-foreground" />
                        <p class="text-sm font-medium text-foreground">
                            {{
                                __(
                                    'You do not have permission to start a conversation.',
                                )
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                __(
                                    'Ask an administrator to grant the messages create permission to your role.',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        v-else-if="props.contacts.length === 0"
                        class="flex flex-col items-center gap-2 rounded-md border border-dashed border-border px-4 py-6 text-center"
                    >
                        <Users class="size-8 text-muted-foreground" />
                        <p class="text-sm font-medium text-foreground">
                            {{ __('No other users available yet.') }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                __(
                                    'Recipients only show other active users. Create another user first if you need someone to message.',
                                )
                            }}
                        </p>
                    </div>

                    <ul
                        v-else
                        class="max-h-48 divide-y divide-border/50 overflow-y-auto rounded-md border border-border"
                        role="list"
                    >
                        <li
                            v-for="contact in filteredContacts"
                            :key="contact.id"
                        >
                            <div
                                role="button"
                                tabindex="0"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                                :class="{
                                    'bg-muted/40': isSelected(contact.id),
                                }"
                                @click="toggleContact(contact.id)"
                                @keydown.enter.prevent="
                                    toggleContact(contact.id)
                                "
                                @keydown.space.prevent="
                                    toggleContact(contact.id)
                                "
                            >
                                <Checkbox
                                    :checked="isSelected(contact.id)"
                                    :aria-label="
                                        __('Select :name', {
                                            name: contact.name,
                                        })
                                    "
                                    tabindex="-1"
                                    @click.stop
                                    @update:checked="
                                        (checked) =>
                                            setContactSelection(
                                                contact.id,
                                                checked,
                                            )
                                    "
                                />

                                <Avatar class="size-7 shrink-0">
                                    <AvatarImage
                                        v-if="contact.avatar"
                                        :src="contact.avatar"
                                        :alt="contact.name"
                                    />
                                    <AvatarFallback class="text-xs">
                                        {{ getInitials(contact.name) }}
                                    </AvatarFallback>
                                </Avatar>

                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium text-foreground"
                                    >
                                        {{ contact.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        @{{ contact.username }}
                                    </p>
                                </div>
                            </div>
                        </li>

                        <li
                            v-if="filteredContacts.length === 0"
                            class="px-3 py-4 text-center text-sm text-muted-foreground"
                        >
                            {{ __('No contacts match your search.') }}
                        </li>
                    </ul>

                    <p
                        v-if="errors.participant_ids"
                        class="text-xs text-destructive"
                        role="alert"
                    >
                        {{ errors.participant_ids }}
                    </p>
                </div>

                <!-- Subject (optional) -->
                <div class="flex flex-col gap-1.5">
                    <Label for="nc-subject">
                        {{ __('Subject') }}
                        <span class="ml-1 text-xs text-muted-foreground">
                            ({{ __('Optional') }})
                        </span>
                    </Label>
                    <Input
                        id="nc-subject"
                        v-model="subject"
                        :disabled="!props.canCreateConversation"
                        :placeholder="__('e.g. Q3 budget review…')"
                        autocomplete="off"
                    />
                    <p
                        v-if="errors.subject"
                        class="text-xs text-destructive"
                        role="alert"
                    >
                        {{ errors.subject }}
                    </p>
                </div>

                <!-- First message -->
                <div class="flex flex-col gap-1.5">
                    <Label for="nc-body">{{ __('Message') }}</Label>
                    <Textarea
                        id="nc-body"
                        v-model="body"
                        :disabled="!props.canCreateConversation"
                        :placeholder="__('Write your first message…')"
                        rows="4"
                        class="resize-none"
                    />
                    <p
                        v-if="errors.message"
                        class="text-xs text-destructive"
                        role="alert"
                    >
                        {{ errors.message }}
                    </p>
                </div>
            </div>

            <DialogFooter
                class="mt-2 gap-2 border-t border-border pt-4 sm:gap-2"
            >
                <Button
                    variant="outline"
                    :disabled="processing"
                    @click="emit('update:open', false)"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button :disabled="!canSubmit" @click="void submit()">
                    <Spinner v-if="processing" class="size-4" />
                    {{ __('Start conversation') }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
