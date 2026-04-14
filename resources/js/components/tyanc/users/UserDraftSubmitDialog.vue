<script setup lang="ts">
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

const props = defineProps<{
    loading?: boolean;
    error?: string;
}>();

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
                <DialogTitle>{{ __('Submit draft for approval') }}</DialogTitle>
                <DialogDescription>
                    {{
                        __(
                            'Your saved changes will be sent for review. Provide a reason to help the reviewer understand the intent.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="draft-submit-note">
                    {{ __('Request reason') }}
                </Label>
                <Textarea
                    id="draft-submit-note"
                    v-model="note"
                    :rows="3"
                    :placeholder="
                        __('Explain why these changes should be approved…')
                    "
                    :disabled="props.loading"
                />
                <FormFieldSupport
                    :hint="
                        __('Required. Visible to whoever reviews this request.')
                    "
                    :error="props.error"
                />
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
                    {{ __('Submit for approval') }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
