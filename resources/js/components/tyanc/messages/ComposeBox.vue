<script setup lang="ts">
import { unrefElement } from '@vueuse/core';
import { SendHorizontal } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, useTemplateRef, ref, watch } from 'vue';
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

let typingTimer: ReturnType<typeof setTimeout> | null = null;

async function focusTextarea(): Promise<void> {
    await nextTick();

    const element = unrefElement(textareaRef);

    if (element instanceof HTMLTextAreaElement) {
        element.focus();
    }
}

watch(body, (value) => {
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
                :rows="2"
                :placeholder="__('Type your message…')"
                :disabled="props.disabled || props.sending"
                class="flex-1 resize-none text-sm"
                :aria-label="__('Type your message…')"
                @keydown="handleKeydown"
            />

            <Button
                type="button"
                size="icon"
                class="mb-0.5 shrink-0"
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
