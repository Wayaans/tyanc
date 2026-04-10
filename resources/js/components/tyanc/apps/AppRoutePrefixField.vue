<script setup lang="ts">
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    modelValue: string;
    error?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { __ } = useTranslations();
</script>

<template>
    <div class="grid gap-2">
        <Label for="app-route-prefix">{{ __('Route prefix') }}</Label>
        <Input
            id="app-route-prefix"
            type="text"
            placeholder="my-app"
            :model-value="props.modelValue"
            :disabled="props.disabled"
            @update:model-value="emit('update:modelValue', String($event))"
        />
        <FormFieldSupport
            :hint="
                __(
                    'URL segment used to prefix this app\'s routes (e.g. /my-app/…).',
                )
            "
            :error="props.error"
        />
    </div>
</template>
