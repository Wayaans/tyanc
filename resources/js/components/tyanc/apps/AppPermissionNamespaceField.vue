<script setup lang="ts">
import FormFieldSupport from '@/components/FormFieldSupport.vue';
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
        <Label for="app-permission-namespace">
            {{ __('Permission namespace') }}
        </Label>
        <Input
            id="app-permission-namespace"
            type="text"
            placeholder="my-app"
            :model-value="props.modelValue"
            :disabled="props.disabled"
            @update:model-value="emit('update:modelValue', String($event))"
        />
        <FormFieldSupport
            :hint="
                __(
                    'Namespace prefix used to scope permissions for this app (e.g. my-app.users.create).',
                )
            "
            :error="props.error"
        />
    </div>
</template>
