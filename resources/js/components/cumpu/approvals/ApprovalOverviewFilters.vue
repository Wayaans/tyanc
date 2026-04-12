<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Filter, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/lib/translations';
import type { ApprovalStatus } from '@/types';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

type RouteFactory = (options?: RouteQueryOptions) => RouteDefinition<'get'>;

type FilterState = {
    status: ApprovalStatus | '';
    app_key: string;
    search: string;
    assignee: string;
    escalated: boolean;
    reassigned: boolean;
    overdue: boolean;
};

const props = defineProps<{
    route: RouteFactory;
    only: string[];
    initial?: Partial<FilterState>;
    appOptions?: Array<{ value: string; label: string }>;
}>();

const { __ } = useTranslations();

const allStatuses: Array<{ value: ApprovalStatus | ''; label: string }> = [
    { value: '', label: 'All statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_review', label: 'In review' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
];

const filters = ref<FilterState>({
    status: props.initial?.status ?? '',
    app_key: props.initial?.app_key ?? '',
    search: props.initial?.search ?? '',
    assignee: props.initial?.assignee ?? '',
    escalated: props.initial?.escalated ?? false,
    reassigned: props.initial?.reassigned ?? false,
    overdue: props.initial?.overdue ?? false,
});

const isDirty = computed(
    () =>
        filters.value.status !== '' ||
        filters.value.app_key !== '' ||
        filters.value.search !== '' ||
        filters.value.assignee !== '' ||
        filters.value.escalated ||
        filters.value.reassigned ||
        filters.value.overdue,
);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    const query: Record<string, string> = {};

    if (filters.value.status) {
        query['filter[status]'] = filters.value.status;
    }
    if (filters.value.app_key) {
        query['filter[app_key]'] = filters.value.app_key;
    }
    if (filters.value.search) {
        query['filter[search]'] = filters.value.search;
    }
    if (filters.value.assignee) {
        query['filter[assignee]'] = filters.value.assignee;
    }
    if (filters.value.escalated) {
        query['filter[escalated]'] = '1';
    }
    if (filters.value.reassigned) {
        query['filter[reassigned]'] = '1';
    }
    if (filters.value.overdue) {
        query['filter[overdue]'] = '1';
    }

    router.get(
        props.route({ query }).url,
        {},
        { preserveScroll: true, only: props.only },
    );
}

function resetFilters() {
    filters.value = {
        status: '',
        app_key: '',
        search: '',
        assignee: '',
        escalated: false,
        reassigned: false,
        overdue: false,
    };
    applyFilters();
}

watch(
    () => filters.value.search,
    () => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(applyFilters, 350);
    },
);

watch(
    () => [
        filters.value.status,
        filters.value.app_key,
        filters.value.assignee,
        filters.value.escalated,
        filters.value.reassigned,
        filters.value.overdue,
    ],
    () => {
        applyFilters();
    },
);
</script>

<template>
    <div
        class="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3"
    >
        <div class="flex items-center gap-1.5 text-muted-foreground">
            <Filter class="size-3.5" />
            <span class="text-xs font-medium">{{ __('Filters') }}</span>
        </div>

        <!-- Search -->
        <div class="min-w-40 flex-1">
            <Input
                v-model="filters.search"
                :placeholder="__('Search by subject…')"
                class="h-8 text-sm"
            />
        </div>

        <!-- Status -->
        <div class="w-36">
            <Select
                :model-value="filters.status"
                @update:model-value="
                    filters.status = ($event === '_all' ? '' : $event) as
                        | ApprovalStatus
                        | ''
                "
            >
                <SelectTrigger class="h-8 text-sm">
                    <SelectValue :placeholder="__('Status')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="s in allStatuses"
                        :key="s.value"
                        :value="s.value === '' ? '_all' : s.value"
                    >
                        {{ __(s.label) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- App filter -->
        <div
            v-if="props.appOptions && props.appOptions.length > 0"
            class="w-36"
        >
            <Select
                :model-value="filters.app_key"
                @update:model-value="
                    filters.app_key = $event === '_all' ? '' : $event
                "
            >
                <SelectTrigger class="h-8 text-sm">
                    <SelectValue :placeholder="__('App')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="_all">{{ __('All apps') }}</SelectItem>
                    <SelectItem
                        v-for="app in props.appOptions"
                        :key="app.value"
                        :value="app.value"
                    >
                        {{ app.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Assignee search -->
        <div class="w-36">
            <Input
                v-model="filters.assignee"
                :placeholder="__('Assignee…')"
                class="h-8 text-sm"
            />
        </div>

        <!-- Boolean flags -->
        <div class="flex items-center gap-4">
            <label class="flex cursor-pointer items-center gap-1.5">
                <Checkbox
                    :model-value="filters.escalated"
                    @update:model-value="filters.escalated = Boolean($event)"
                />
                <span class="text-xs text-muted-foreground">{{
                    __('Escalated')
                }}</span>
            </label>
            <label class="flex cursor-pointer items-center gap-1.5">
                <Checkbox
                    :model-value="filters.reassigned"
                    @update:model-value="filters.reassigned = Boolean($event)"
                />
                <span class="text-xs text-muted-foreground">{{
                    __('Reassigned')
                }}</span>
            </label>
            <label class="flex cursor-pointer items-center gap-1.5">
                <Checkbox
                    :model-value="filters.overdue"
                    @update:model-value="filters.overdue = Boolean($event)"
                />
                <span class="text-xs text-muted-foreground">{{
                    __('Overdue')
                }}</span>
            </label>
        </div>

        <!-- Clear -->
        <Button
            v-if="isDirty"
            variant="ghost"
            size="sm"
            class="h-8 gap-1 text-xs text-muted-foreground hover:text-foreground"
            @click="resetFilters"
        >
            <X class="size-3" />
            {{ __('Clear') }}
        </Button>
    </div>
</template>
