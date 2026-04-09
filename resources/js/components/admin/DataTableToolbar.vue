<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Filter, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTableFilterSheet from '@/components/admin/DataTableFilterSheet.vue';
import DataTableViewOptions from '@/components/admin/DataTableViewOptions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/translations';
import type { DataTableFilterDefinition } from '@/types';

const emit = defineEmits<{
    applyFilters: [];
    clearFilters: [];
    removeFilter: [id: string];
    'update:filter': [id: string, value: string | string[]];
}>();

const props = defineProps<{
    table: Table<unknown>;
    filters: DataTableFilterDefinition[];
    activeFilters: Record<string, string | string[]>;
    draftFilters: Record<string, string | string[]>;
    activeFilterCount: number;
    selectedRowCount: number;
}>();

const { __ } = useTranslations();
const isFilterSheetOpen = ref(false);

const badges = computed(() =>
    Object.entries(props.activeFilters).flatMap(([id, value]) => {
        const definition = props.filters.find((filter) => filter.id === id);

        if (!definition) {
            return [];
        }

        const values = Array.isArray(value) ? value : [value];

        return values.map((item) => ({
            id,
            label: `${__(definition.label)}: ${__(item)}`,
        }));
    }),
);
</script>

<template>
    <div
        class="flex flex-col gap-3 border-b border-sidebar-border/70 px-4 py-4"
    >
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2 rounded-xl"
                    @click="isFilterSheetOpen = true"
                >
                    <Filter class="size-4" />
                    {{ __('Filters') }}
                    <Badge
                        v-if="props.activeFilterCount > 0"
                        variant="secondary"
                        class="rounded-full"
                    >
                        {{ props.activeFilterCount }}
                    </Badge>
                </Button>

                <Button
                    v-if="props.activeFilterCount > 0"
                    variant="ghost"
                    size="sm"
                    class="rounded-xl"
                    @click="emit('clearFilters')"
                >
                    {{ __('Clear filters') }}
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-2 md:justify-end">
                <Badge
                    v-if="props.selectedRowCount > 0"
                    variant="outline"
                    class="rounded-full"
                >
                    {{
                        __(':count selected', {
                            count: String(props.selectedRowCount),
                        })
                    }}
                </Badge>
                <DataTableViewOptions :table="props.table" />
            </div>
        </div>

        <div v-if="badges.length > 0" class="flex flex-wrap items-center gap-2">
            <Badge
                v-for="badge in badges"
                :key="badge.label"
                variant="outline"
                class="gap-1 rounded-full bg-background/80"
            >
                <SlidersHorizontal class="size-3" />
                {{ badge.label }}
                <button
                    type="button"
                    class="rounded-full p-0.5 text-muted-foreground transition hover:text-foreground"
                    @click="emit('removeFilter', badge.id)"
                >
                    <X class="size-3" />
                    <span class="sr-only">
                        {{ __('Remove filter') }}
                    </span>
                </button>
            </Badge>
        </div>

        <DataTableFilterSheet
            v-model:open="isFilterSheetOpen"
            :filters="props.filters"
            :values="props.draftFilters"
            @apply="emit('applyFilters')"
            @clear="emit('clearFilters')"
            @update:filter="(id, value) => emit('update:filter', id, value)"
        />
    </div>
</template>
