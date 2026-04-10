<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/lib/translations';
import type { SelectOption } from '@/types';

type CatalogFilters = {
    app: string;
    status: string;
};

const props = defineProps<{
    modelValue: CatalogFilters;
    apps: SelectOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: CatalogFilters];
}>();

const ALL_OPTION = '__all__';
const { __ } = useTranslations();

const statusOptions: SelectOption[] = [
    { value: 'synced', label: __('Synced') },
    { value: 'orphaned', label: __('Orphaned') },
    { value: 'missing', label: __('Missing') },
];

function toSelectValue(value: string): string {
    return value || ALL_OPTION;
}

function update<K extends keyof CatalogFilters>(key: K, raw: string) {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: raw === ALL_OPTION ? '' : raw,
    });
}

function clearAll() {
    emit('update:modelValue', { app: '', status: '' });
}

const hasActiveFilters = computed(
    () => Boolean(props.modelValue.app) || Boolean(props.modelValue.status),
);

const activeCount = computed(
    () => (props.modelValue.app ? 1 : 0) + (props.modelValue.status ? 1 : 0),
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Select
            :model-value="toSelectValue(props.modelValue.app)"
            @update:model-value="update('app', String($event))"
        >
            <SelectTrigger class="h-8 w-40 text-xs">
                <SelectValue :placeholder="__('All apps')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="ALL_OPTION">{{
                    __('All apps')
                }}</SelectItem>
                <SelectItem
                    v-for="app in props.apps"
                    :key="app.value"
                    :value="app.value"
                >
                    {{ app.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select
            :model-value="toSelectValue(props.modelValue.status)"
            @update:model-value="update('status', String($event))"
        >
            <SelectTrigger class="h-8 w-40 text-xs">
                <SelectValue :placeholder="__('All statuses')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="ALL_OPTION">
                    {{ __('All statuses') }}
                </SelectItem>
                <SelectItem
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <template v-if="hasActiveFilters">
            <Badge variant="secondary" class="rounded-full text-xs">
                {{ __(':n active', { n: String(activeCount) }) }}
            </Badge>
            <Button
                variant="ghost"
                size="sm"
                class="h-7 gap-1 px-2 text-xs text-muted-foreground"
                @click="clearAll"
            >
                <X class="size-3" />
                {{ __('Clear') }}
            </Button>
        </template>
    </div>
</template>
