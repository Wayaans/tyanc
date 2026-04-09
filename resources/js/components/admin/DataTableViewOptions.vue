<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Columns3 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    table: Table<unknown>;
}>();

const { __ } = useTranslations();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="gap-2 rounded-xl">
                <Columns3 class="size-4" />
                {{ __('Columns') }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52 rounded-xl">
            <DropdownMenuLabel>{{ __('View options') }}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuCheckboxItem
                v-for="column in props.table
                    .getAllLeafColumns()
                    .filter((item) => item.getCanHide())"
                :key="column.id"
                :model-value="column.getIsVisible()"
                @update:model-value="column.toggleVisibility(Boolean($event))"
            >
                {{ __(String(column.columnDef.meta?.label ?? column.id)) }}
            </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
