<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslations } from '@/lib/translations';
import type { DataTableFilterDefinition } from '@/types';

const ANY_OPTION = '__any__';
const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    apply: [];
    clear: [];
    'update:filter': [id: string, value: string | string[]];
}>();

const props = defineProps<{
    filters: DataTableFilterDefinition[];
    values: Record<string, string | string[]>;
}>();

const { __ } = useTranslations();

const hasFilters = computed(() => props.filters.length > 0);

const resolveValue = (id: string): string => {
    const value = props.values[id];

    if (Array.isArray(value)) {
        return value[0] ?? ANY_OPTION;
    }

    return value ?? ANY_OPTION;
};

const updateFilter = (id: string, value: string): void => {
    emit('update:filter', id, value === ANY_OPTION ? '' : value);
};

const handleApply = (): void => {
    emit('apply');
    open.value = false;
};

const handleClear = (): void => {
    emit('clear');
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex flex-col gap-0 sm:max-w-md">
            <SheetHeader class="px-6 pt-6">
                <SheetTitle>{{ __('Filters') }}</SheetTitle>
                <SheetDescription>
                    {{
                        __(
                            'Refine the current table view without leaving the page.',
                        )
                    }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1 space-y-5 overflow-y-auto px-6 py-6">
                <div
                    v-if="!hasFilters"
                    class="rounded-2xl border border-dashed border-sidebar-border/70 bg-sidebar/20 px-4 py-8 text-sm text-muted-foreground"
                >
                    {{ __('No filters available for this table.') }}
                </div>

                <div
                    v-for="filter in props.filters"
                    :key="filter.id"
                    class="grid gap-2"
                >
                    <Label :for="filter.id">{{ __(filter.label) }}</Label>

                    <Input
                        v-if="filter.type === 'text'"
                        :id="filter.id"
                        :model-value="resolveValue(filter.id)"
                        :placeholder="
                            filter.placeholder
                                ? __(filter.placeholder)
                                : __('Search')
                        "
                        class="rounded-xl"
                        @update:model-value="
                            updateFilter(filter.id, String($event))
                        "
                    />

                    <Select
                        v-else
                        :model-value="resolveValue(filter.id)"
                        @update:model-value="
                            updateFilter(filter.id, String($event))
                        "
                    >
                        <SelectTrigger
                            :id="filter.id"
                            class="w-full rounded-xl"
                        >
                            <SelectValue
                                :placeholder="
                                    __('Select :label', {
                                        label: __(filter.label),
                                    })
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ANY_OPTION">{{
                                __('Any')
                            }}</SelectItem>
                            <SelectItem
                                v-for="option in filter.options ?? []"
                                :key="`${filter.id}-${option.value}`"
                                :value="option.value"
                            >
                                {{ __(option.label) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <SheetFooter
                class="border-t border-sidebar-border/70 px-6 py-4 sm:justify-between"
            >
                <Button variant="ghost" @click="handleClear">
                    {{ __('Clear filters') }}
                </Button>
                <Button class="rounded-xl" @click="handleApply">
                    {{ __('Apply filters') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
