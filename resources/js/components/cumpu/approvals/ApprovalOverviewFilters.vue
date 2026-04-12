<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Filter, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
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
    { value: 'approved', label: 'Approved – grant ready' },
    { value: 'consumed', label: 'Consumed – grant used' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
];

function makeFilters(initial?: Partial<FilterState>): FilterState {
    return {
        status: initial?.status ?? '',
        app_key: initial?.app_key ?? '',
        search: initial?.search ?? '',
        assignee: initial?.assignee ?? '',
        escalated: initial?.escalated ?? false,
        reassigned: initial?.reassigned ?? false,
        overdue: initial?.overdue ?? false,
    };
}

const filters = ref<FilterState>(makeFilters(props.initial));

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
const suppressWatchers = ref(false);

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
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }

    suppressWatchers.value = true;
    filters.value = makeFilters();
    applyFilters();

    void nextTick(() => {
        suppressWatchers.value = false;
    });
}

watch(
    () => props.initial,
    (initial) => {
        suppressWatchers.value = true;
        filters.value = makeFilters(initial);

        void nextTick(() => {
            suppressWatchers.value = false;
        });
    },
    { deep: true },
);

watch(
    () => filters.value.search,
    () => {
        if (suppressWatchers.value) {
            return;
        }

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
        if (suppressWatchers.value) {
            return;
        }

        applyFilters();
    },
);
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3"
    >
        <div class="overflow-x-auto">
            <div class="flex items-center gap-2.5">
                <div
                    class="flex shrink-0 items-center gap-1.5 text-muted-foreground"
                >
                    <Filter class="size-3.5" />
                    <span class="text-xs font-medium">{{ __('Filters') }}</span>
                </div>

                <Input
                    v-model="filters.search"
                    :placeholder="__('Search by subject…')"
                    class="h-9 min-w-0 flex-1 text-sm"
                />

                <Select
                    :model-value="filters.status"
                    @update:model-value="
                        filters.status = ($event === '_all' ? '' : $event) as
                            | ApprovalStatus
                            | ''
                    "
                >
                    <SelectTrigger class="h-9 w-44 shrink-0 text-sm">
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

                <Select
                    v-if="props.appOptions && props.appOptions.length > 0"
                    :model-value="filters.app_key"
                    @update:model-value="
                        filters.app_key = $event === '_all' ? '' : $event
                    "
                >
                    <SelectTrigger class="h-9 w-32 shrink-0 text-sm">
                        <SelectValue :placeholder="__('App')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="_all">{{
                            __('All apps')
                        }}</SelectItem>
                        <SelectItem
                            v-for="app in props.appOptions"
                            :key="app.value"
                            :value="app.value"
                        >
                            {{ app.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Input
                    v-model="filters.assignee"
                    :placeholder="__('Assignee…')"
                    class="h-9 w-32 shrink-0 text-sm"
                />

                <div class="mx-0.5 h-4 w-px shrink-0 bg-border" />

                <label
                    class="flex shrink-0 cursor-pointer items-center gap-1.5"
                >
                    <Checkbox
                        :model-value="filters.overdue"
                        @update:model-value="filters.overdue = Boolean($event)"
                    />
                    <span class="text-xs text-muted-foreground">{{
                        __('Overdue')
                    }}</span>
                </label>
                <label
                    class="flex shrink-0 cursor-pointer items-center gap-1.5"
                >
                    <Checkbox
                        :model-value="filters.escalated"
                        @update:model-value="
                            filters.escalated = Boolean($event)
                        "
                    />
                    <span class="text-xs text-muted-foreground">{{
                        __('Escalated')
                    }}</span>
                </label>
                <label
                    class="flex shrink-0 cursor-pointer items-center gap-1.5"
                >
                    <Checkbox
                        :model-value="filters.reassigned"
                        @update:model-value="
                            filters.reassigned = Boolean($event)
                        "
                    />
                    <span class="text-xs text-muted-foreground">{{
                        __('Reassigned')
                    }}</span>
                </label>

                <Button
                    v-if="isDirty"
                    variant="ghost"
                    size="sm"
                    class="ml-auto h-9 shrink-0 gap-1 text-xs text-muted-foreground hover:text-foreground"
                    @click="resetFilters"
                >
                    <X class="size-3" />
                    {{ __('Clear') }}
                </Button>
            </div>
        </div>
    </div>
</template>
