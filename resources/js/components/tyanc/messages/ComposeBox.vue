<script setup lang="ts">
import { unrefElement } from '@vueuse/core';
import { SendHorizontal } from 'lucide-vue-next';
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    useTemplateRef,
    ref,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    disabled?: boolean;
    sending?: boolean;
    error?: string | null;
}>();

const emit = defineEmits<{
    send: [body: string];
    typing: [];
}>();

const { __ } = useTranslations();
const body = ref('');
const textareaRef = useTemplateRef('textarea');
const sendButtonRef = useTemplateRef('sendButton');

const MAX_LINES = 15;
const FALLBACK_LINE_HEIGHT_PX = 20;
const FALLBACK_MIN_HEIGHT_PX = 36;

let typingTimer: ReturnType<typeof setTimeout> | null = null;

function resizeTextarea(): void {
    const textarea = unrefElement(textareaRef);
    const sendButton = unrefElement(sendButtonRef);

    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    const style = window.getComputedStyle(textarea);
    const parsedLineHeight = Number.parseFloat(style.lineHeight);
    const lineHeight = Number.isNaN(parsedLineHeight)
        ? FALLBACK_LINE_HEIGHT_PX
        : parsedLineHeight;
    const paddingTop = Number.parseFloat(style.paddingTop);
    const paddingBottom = Number.parseFloat(style.paddingBottom);
    const borderTop = Number.parseFloat(style.borderTopWidth);
    const borderBottom = Number.parseFloat(style.borderBottomWidth);
    const minHeight =
        sendButton instanceof HTMLElement
            ? sendButton.offsetHeight
            : FALLBACK_MIN_HEIGHT_PX;

    // Maximum border-box height for 15 visible lines.
    const maxHeight =
        lineHeight * MAX_LINES +
        paddingTop +
        paddingBottom +
        borderTop +
        borderBottom;

    // Shrink to auto first so scrollHeight reflects only the actual content.
    textarea.style.height = 'auto';
    textarea.style.overflowY = 'hidden';

    // scrollHeight includes padding but not border; add borders back.
    const neededHeight = textarea.scrollHeight + borderTop + borderBottom;
    const nextHeight = Math.max(minHeight, Math.min(neededHeight, maxHeight));

    textarea.style.height = `${nextHeight}px`;
    textarea.style.overflowY = neededHeight > maxHeight ? 'auto' : 'hidden';
}

async function focusTextarea(): Promise<void> {
    await nextTick();

    const element = unrefElement(textareaRef);

    if (element instanceof HTMLTextAreaElement) {
        element.focus();
    }
}

watch(body, (value) => {
    // Resize after the DOM has updated with the new value.
    void nextTick(resizeTextarea);

    if (value.trim() === '') {
        return;
    }

    if (typingTimer !== null) {
        clearTimeout(typingTimer);
    }

    emit('typing');

    typingTimer = setTimeout(() => {
        typingTimer = null;
    }, 2000);
});

// Clear the draft only after a successful send (sending goes true → false with no error).
// This lets the user retry if the send fails without retyping their message.
watch(
    () => props.sending,
    (isSending, wasSending) => {
        if (wasSending && !isSending && !props.error) {
            body.value = '';
        }

        if (wasSending && !isSending) {
            void focusTextarea();
        }
    },
);

onMounted(() => {
    void nextTick(resizeTextarea);
});

onBeforeUnmount(() => {
    if (typingTimer !== null) {
        clearTimeout(typingTimer);
    }
});

function handleSend() {
    const trimmed = body.value.trim();

    if (trimmed === '' || props.disabled || props.sending) {
        return;
    }

    emit('send', trimmed);
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
    }
}
</script>

<template>
    <div class="border-t border-border bg-background px-4 py-3">
        <div class="flex items-end gap-2">
            <Textarea
                ref="textarea"
                v-model="body"
                :rows="1"
                :placeholder="__('Type your message…')"
                :disabled="props.disabled || props.sending"
                class="flex-1 py-1.5 text-sm leading-5"
                :aria-label="__('Type your message…')"
                @keydown="handleKeydown"
            />

            <Button
                ref="sendButton"
                type="button"
                size="icon"
                class="shrink-0"
                :disabled="!body.trim() || props.disabled || props.sending"
                :aria-label="__('Send message')"
                @click="handleSend"
            >
                <SendHorizontal class="size-4" />
            </Button>
        </div>

        <p v-if="props.error" class="mt-1.5 text-xs text-destructive">
            {{ props.error }}
        </p>
        <p v-else class="mt-1.5 text-xs text-muted-foreground">
            {{ __('Press Enter to send, Shift+Enter for a new line.') }}
        </p>
    </div>
</template>
