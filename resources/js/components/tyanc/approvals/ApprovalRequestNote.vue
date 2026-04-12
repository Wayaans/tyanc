<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    modelValue?: string;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { __ } = useTranslations();

const value = useVModel(props, 'modelValue', emit, {
    passive: true,
    defaultValue: '',
});
</script>

<template>
    <div class="grid gap-2">
        <Label for="request_note">
            {{ __('Approval request note') }}
            <span class="ml-1 text-xs font-normal text-muted-foreground">
                {{ __('(optional)') }}
            </span>
        </Label>
        <Textarea
            id="request_note"
            v-model="value"
            name="request_note"
            :rows="3"
            :placeholder="__('Optional note for the approver…')"
        />
        <FormFieldSupport
            :hint="__('Visible to whoever reviews this request.')"
            :error="error"
        />
    </div>
</template>
