<script setup lang="ts">
import { computed } from 'vue';
import { ComboboxSelect } from '@/components/ui/combobox';

const props = defineProps<{
    timezones: string[];
    modelValue?: string;
    name?: string;
    id?: string;
    placeholder?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const options = computed(() =>
    props.timezones.map((tz) => ({ value: tz, label: tz })),
);
</script>

<template>
    <ComboboxSelect
        :options="options"
        :model-value="modelValue"
        :name="name"
        :id="id"
        :placeholder="placeholder ?? 'Select timezone…'"
        search-placeholder="Search timezones…"
        :disabled="disabled"
        class="w-full"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
