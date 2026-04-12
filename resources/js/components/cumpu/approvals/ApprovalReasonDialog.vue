<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import DialogScrollContent from '@/components/ui/dialog/DialogScrollContent.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';

export type RelevantRequestLink = {
    id: string;
    action_label: string;
    detail_url: string | null;
};

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        actionLabel?: string;
        loading?: boolean;
        error?: string;
        relevantRequest?: RelevantRequestLink | null;
    }>(),
    {
        description: undefined,
        actionLabel: undefined,
        loading: false,
        error: undefined,
        relevantRequest: null,
    },
);

const open = defineModel<boolean>('open', { default: false });
const note = defineModel<string>('note', { default: '' });

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

const { __ } = useTranslations();

const hasNote = computed(() => note.value.trim() !== '');

function handleConfirm() {
    if (!hasNote.value || props.loading) {
        return;
    }

    note.value = note.value.trim();
    emit('confirm');
}

function handleCancel() {
    open.value = false;
    emit('cancel');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ props.title }}</DialogTitle>
                <DialogDescription v-if="props.description">
                    {{ props.description }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Informational link to an existing related request -->
                <a
                    v-if="props.relevantRequest?.detail_url"
                    :href="props.relevantRequest.detail_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-2 rounded-xl border border-amber-200/60 bg-amber-50/50 px-3 py-2.5 text-sm text-amber-800 transition-colors hover:bg-amber-100/60 dark:border-amber-500/20 dark:bg-amber-500/[0.07] dark:text-amber-300 dark:hover:bg-amber-500/15"
                >
                    <span class="min-w-0 flex-1 truncate">
                        {{ props.relevantRequest.action_label }}
                    </span>
                    <ExternalLink class="size-3.5 shrink-0 opacity-70" />
                </a>

                <!-- Note field -->
                <div class="grid gap-2">
                    <Label for="approval-reason-note">
                        {{ __('Request reason') }}
                    </Label>
                    <Textarea
                        id="approval-reason-note"
                        v-model="note"
                        :rows="3"
                        :placeholder="
                            __('Explain why this action should be approved…')
                        "
                        :disabled="props.loading"
                    />
                    <FormFieldSupport
                        :hint="
                            __(
                                'Required. Visible to whoever reviews this request.',
                            )
                        "
                        :error="props.error"
                    />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    variant="outline"
                    :disabled="props.loading"
                    @click="handleCancel"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button
                    :disabled="props.loading || !hasNote"
                    @click="handleConfirm"
                >
                    <Spinner v-if="props.loading" />
                    {{ props.actionLabel ?? __('Submit request') }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
