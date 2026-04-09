<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/lib/translations';
import type { DataTableMeta } from '@/types';

const props = defineProps<{
    table: Table<unknown>;
    meta: DataTableMeta;
}>();

const { __ } = useTranslations();
const pageSizeOptions = ['5', '10', '15', '25'];
</script>

<template>
    <div
        class="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div
            class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
        >
            <span>{{ __('Rows per page') }}</span>
            <Select
                :model-value="String(props.meta.per_page)"
                @update:model-value="props.table.setPageSize(Number($event))"
            >
                <SelectTrigger class="h-9 w-[88px] rounded-xl">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in pageSizeOptions"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <span>
                {{
                    __('Showing :from–:to of :total', {
                        from: String(props.meta.from ?? 0),
                        to: String(props.meta.to ?? 0),
                        total: String(props.meta.total),
                    })
                }}
            </span>
        </div>

        <div class="flex items-center justify-between gap-3 sm:justify-end">
            <span class="text-sm text-muted-foreground">
                {{
                    __('Page :page of :pages', {
                        page: String(props.meta.page),
                        pages: String(props.meta.last_page),
                    })
                }}
            </span>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    class="rounded-xl"
                    :disabled="props.meta.page <= 1"
                    @click="props.table.previousPage()"
                >
                    <ChevronLeft class="size-4" />
                    <span class="sr-only">{{ __('Previous page') }}</span>
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="rounded-xl"
                    :disabled="props.meta.page >= props.meta.last_page"
                    @click="props.table.nextPage()"
                >
                    <ChevronRight class="size-4" />
                    <span class="sr-only">{{ __('Next page') }}</span>
                </Button>
            </div>
        </div>
    </div>
</template>
