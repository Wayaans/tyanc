<script setup lang="ts">
import { Filter, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { ComboboxSelect } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/lib/translations';

type Option = { value: string; label: string };

export type ApprovalRuleFilters = {
    search: string;
    app: string;
    resource: string;
    action: string;
};

const props = defineProps<{
    modelValue: ApprovalRuleFilters;
    appOptions: Option[];
    resourceOptions: Option[];
    actionOptions: Option[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: ApprovalRuleFilters];
}>();

const { __ } = useTranslations();

const ALL_APPS = '__all_apps__';
const ALL_RESOURCES = '__all_resources__';
const ALL_ACTIONS = '__all_actions__';

const appOptionsWithAll = computed<Option[]>(() => [
    { value: ALL_APPS, label: __('All apps') },
    ...props.appOptions,
]);

const resourceOptionsWithAll = computed<Option[]>(() => [
    { value: ALL_RESOURCES, label: __('All resources') },
    ...props.resourceOptions,
]);

const actionOptionsWithAll = computed<Option[]>(() => [
    { value: ALL_ACTIONS, label: __('All actions') },
    ...props.actionOptions,
]);

function fromFilterValue(value: string, sentinel: string): string {
    return value === sentinel ? '' : value;
}

function update<K extends keyof ApprovalRuleFilters>(
    key: K,
    value: string,
    sentinel?: string,
) {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: sentinel ? fromFilterValue(value, sentinel) : value,
    });
}

function clearAll() {
    emit('update:modelValue', {
        search: '',
        app: '',
        resource: '',
        action: '',
    });
}

const hasActiveFilters = computed(
    () =>
        props.modelValue.search.trim() !== '' ||
        props.modelValue.app !== '' ||
        props.modelValue.resource !== '' ||
        props.modelValue.action !== '',
);
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3"
    >
        <div class="flex flex-wrap items-center gap-2.5">
            <div
                class="flex shrink-0 items-center gap-1.5 text-muted-foreground"
            >
                <Filter class="size-3.5" />
                <span class="text-xs font-medium">{{ __('Filters') }}</span>
            </div>

            <Input
                :model-value="props.modelValue.search"
                :placeholder="__('Search rules…')"
                class="h-8 min-w-36 flex-1 text-sm"
                @update:model-value="update('search', String($event))"
            />

            <ComboboxSelect
                :model-value="props.modelValue.app"
                :options="appOptionsWithAll"
                :placeholder="__('All apps')"
                :search-placeholder="__('Search apps…')"
                class="h-8 w-36 text-xs"
                @update:model-value="update('app', $event, ALL_APPS)"
            />

            <ComboboxSelect
                :model-value="props.modelValue.resource"
                :options="resourceOptionsWithAll"
                :placeholder="__('All resources')"
                :search-placeholder="__('Search resources…')"
                class="h-8 w-40 text-xs"
                @update:model-value="update('resource', $event, ALL_RESOURCES)"
            />

            <ComboboxSelect
                :model-value="props.modelValue.action"
                :options="actionOptionsWithAll"
                :placeholder="__('All actions')"
                :search-placeholder="__('Search actions…')"
                class="h-8 w-40 text-xs"
                @update:model-value="update('action', $event, ALL_ACTIONS)"
            />

            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                class="h-8 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                @click="clearAll"
            >
                <X class="size-3" />
                {{ __('Clear') }}
            </Button>
        </div>
    </div>
</template>
