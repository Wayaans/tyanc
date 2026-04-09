<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { BellRing, Blocks, LayoutPanelTop, Sparkles } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/admin/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { dashboard as demoDashboard } from '@/routes/demo';
import type { DataTablePayload } from '@/types';

type ExampleRow = {
    id: string;
    name: string;
    category: string;
    maturity: string;
    surface: string;
    updated_at: string;
};

const props = defineProps<{
    examplesTable: DataTablePayload<ExampleRow>;
}>();

const { __, locale } = useTranslations();
const { dashboardBreadcrumbs } = useAppNavigation();
const columnHelper = createColumnHelper<ExampleRow>();
const activeDialog = ref<'create' | 'edit' | null>(null);
const isSheetOpen = ref(false);
const formOwner = ref('Design systems');
const formComponent = ref('Navigation switcher');
const formReleaseState = ref('Beta');

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const maturityClassName = (maturity: string): string => {
    if (maturity === 'Ready') {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (maturity === 'Beta') {
        return 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }

    return 'border-neutral-500/20 bg-neutral-500/10 text-neutral-700 dark:text-neutral-300';
};

const featureCards = computed(() => [
    {
        title: __('Fast feedback loops'),
        description: __(
            'Use this sandbox to verify grayscale surfaces, spacing, and interaction patterns before shipping to Tyanc.',
        ),
        icon: Sparkles,
    },
    {
        title: __('Dialog-first editing'),
        description: __(
            'Create and edit examples stay inside Dialogs so the page context remains stable.',
        ),
        icon: LayoutPanelTop,
    },
    {
        title: __('Sheet-based filters'),
        description: __(
            'Reserve Sheets for filters and secondary controls, not CRUD forms.',
        ),
        icon: Blocks,
    },
]);

const badgeExamples = computed(() => [
    { label: __('Ready'), className: maturityClassName('Ready') },
    { label: __('Beta'), className: maturityClassName('Beta') },
    { label: __('Preview'), className: maturityClassName('Preview') },
    {
        label: __('Attention'),
        className:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
]);

const dialogTitle = computed(() =>
    activeDialog.value === 'edit' ? __('Edit example') : __('Create example'),
);
const dialogDescription = computed(() =>
    activeDialog.value === 'edit'
        ? __('Review dialog behavior without leaving the dashboard shell.')
        : __('Use Dialogs for focused create flows inside the demo workspace.'),
);

const showSuccessToast = (): void => {
    toast.success(__('Success toast preview'), {
        description: __(
            'The shared toaster is mounted once and ready for module feedback.',
        ),
    });
};

const showErrorToast = (): void => {
    toast.error(__('Error toast preview'), {
        description: __(
            'Use this state for failed saves, invalid imports, or network interruptions.',
        ),
    });
};

const columns = computed<ColumnDef<ExampleRow>[]>(() => [
    columnHelper.display({
        id: 'select',
        enableSorting: false,
        enableHiding: false,
        header: ({ table }: { table: TanStackTable<ExampleRow> }) =>
            h(Checkbox, {
                checked: table.getIsAllPageRowsSelected(),
                'onUpdate:checked': (value: boolean | 'indeterminate') =>
                    table.toggleAllPageRowsSelected(Boolean(value)),
                'aria-label': __('Select all rows'),
            }),
        cell: ({ row }) =>
            h(Checkbox, {
                checked: row.getIsSelected(),
                'onUpdate:checked': (value: boolean | 'indeterminate') =>
                    row.toggleSelected(Boolean(value)),
                'aria-label': __('Select row'),
            }),
        meta: {
            label: 'Selection',
        },
    }),
    columnHelper.accessor('name', {
        header: __('Component'),
        cell: ({ row }) =>
            h('div', { class: 'min-w-44 space-y-1' }, [
                h(
                    'p',
                    { class: 'font-medium text-foreground' },
                    __(row.original.name),
                ),
                h(
                    'p',
                    { class: 'text-xs text-muted-foreground' },
                    __(row.original.surface),
                ),
            ]),
        meta: {
            label: 'Component',
        },
    }),
    columnHelper.accessor('category', {
        header: __('Category'),
        cell: ({ getValue }) => __(String(getValue())),
        meta: {
            label: 'Category',
        },
    }),
    columnHelper.accessor('maturity', {
        header: __('Maturity'),
        cell: ({ getValue }) => {
            const maturity = String(getValue());

            return h(
                Badge,
                {
                    variant: 'outline',
                    class: `rounded-full ${maturityClassName(maturity)}`,
                },
                {
                    default: () => __(maturity),
                },
            );
        },
        meta: {
            label: 'Maturity',
        },
    }),
    columnHelper.accessor('updated_at', {
        header: __('Updated'),
        cell: ({ getValue }) =>
            h(
                'span',
                { class: 'whitespace-nowrap text-muted-foreground' },
                dateFormatter.value.format(new Date(String(getValue()))),
            ),
        meta: {
            label: 'Updated',
        },
    }),
]);
</script>

<template>
    <Head :title="__('Dashboard')" />

    <AppLayout :breadcrumbs="dashboardBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <section
                class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]"
            >
                <Card
                    class="border-sidebar-border/70 bg-sidebar/25 shadow-none"
                >
                    <CardContent class="space-y-4 px-5 py-5 md:px-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" class="rounded-full">
                                {{ __('Demo sandbox') }}
                            </Badge>
                            <Badge variant="outline" class="rounded-full">
                                {{ __('Phase 4') }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                            >
                                {{ __('UI pattern sandbox') }}
                            </h1>
                            <p
                                class="max-w-2xl text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    __(
                                        'Use this page to validate shared UI primitives, responsive states, and translated admin patterns without database dependencies.',
                                    )
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle class="text-base">{{
                            __('Feedback preview')
                        }}</CardTitle>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{
                                __(
                                    'The demo route includes one success toast and one error toast for shared notification styling.',
                                )
                            }}
                        </p>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button class="rounded-xl" @click="showSuccessToast">
                            <BellRing class="size-4" />
                            {{ __('Show success toast') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="rounded-xl"
                            @click="showErrorToast"
                        >
                            <BellRing class="size-4" />
                            {{ __('Show error toast') }}
                        </Button>
                    </CardContent>
                </Card>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <Card
                    v-for="item in featureCards"
                    :key="item.title"
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-xl border border-sidebar-border/70 bg-sidebar/35 text-muted-foreground"
                        >
                            <component :is="item.icon" class="size-4" />
                        </div>
                        <CardTitle class="text-base">{{
                            item.title
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ item.description }}
                        </p>
                    </CardContent>
                </Card>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle class="text-base">{{
                            __('Example form')
                        }}</CardTitle>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{
                                __(
                                    'A compact form preview that shares the same inputs and spacing used across future modules.',
                                )
                            }}
                        </p>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="demo_component_name">{{
                                __('Component name')
                            }}</Label>
                            <Input
                                id="demo_component_name"
                                v-model="formComponent"
                                class="rounded-xl"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="demo_owner">{{ __('Owner') }}</Label>
                            <Input
                                id="demo_owner"
                                v-model="formOwner"
                                class="rounded-xl"
                            />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="demo_release_state">{{
                                __('Release state')
                            }}</Label>
                            <Select v-model="formReleaseState">
                                <SelectTrigger
                                    id="demo_release_state"
                                    class="w-full rounded-xl"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Ready">{{
                                        __('Ready')
                                    }}</SelectItem>
                                    <SelectItem value="Beta">{{
                                        __('Beta')
                                    }}</SelectItem>
                                    <SelectItem value="Preview">{{
                                        __('Preview')
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-wrap gap-3 sm:col-span-2">
                            <Button
                                class="rounded-xl"
                                @click="activeDialog = 'create'"
                            >
                                {{ __('Create example') }}
                            </Button>
                            <Button
                                variant="outline"
                                class="rounded-xl"
                                @click="activeDialog = 'edit'"
                            >
                                {{ __('Edit example') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle class="text-base">{{
                            __('Badge set')
                        }}</CardTitle>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{
                                __(
                                    'Muted badge treatments keep states readable without introducing new accent colors.',
                                )
                            }}
                        </p>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <Badge
                            v-for="badge in badgeExamples"
                            :key="badge.label"
                            variant="outline"
                            class="rounded-full"
                            :class="badge.className"
                        >
                            {{ badge.label }}
                        </Badge>
                    </CardContent>
                </Card>
            </section>

            <section class="space-y-3">
                <div class="space-y-1 px-1">
                    <h2 class="text-lg font-semibold text-foreground">
                        {{ __('Sandbox registry') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'This static DataTable mirrors the shared dashboard table behaviors without relying on stored records.',
                            )
                        }}
                    </p>
                </div>

                <DataTable
                    :columns="columns"
                    :rows="props.examplesTable.rows"
                    :meta="props.examplesTable.meta"
                    :query="props.examplesTable.query"
                    :filters="props.examplesTable.filters"
                    :route="demoDashboard"
                    :only="['examplesTable']"
                    :empty-title="
                        __('No sandbox examples match the current filters.')
                    "
                    :empty-description="
                        __(
                            'Clear the current filters or choose a different maturity state.',
                        )
                    "
                />
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle class="text-base">{{
                            __('Dialog and sheet examples')
                        }}</CardTitle>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{
                                __(
                                    'Create and edit keep users inside Dialogs, while the separate Sheet example stays reserved for secondary controls.',
                                )
                            }}
                        </p>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button
                            class="rounded-xl"
                            @click="activeDialog = 'create'"
                        >
                            {{ __('Open create dialog') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="rounded-xl"
                            @click="activeDialog = 'edit'"
                        >
                            {{ __('Open edit dialog') }}
                        </Button>
                        <Button
                            variant="ghost"
                            class="rounded-xl"
                            @click="isSheetOpen = true"
                        >
                            {{ __('Open filter sheet') }}
                        </Button>
                    </CardContent>
                </Card>
            </section>
        </div>

        <Dialog
            :open="activeDialog !== null"
            @update:open="activeDialog = $event ? activeDialog : null"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        {{ dialogDescription }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="dialog_component_name">{{
                            __('Component name')
                        }}</Label>
                        <Input
                            id="dialog_component_name"
                            :model-value="formComponent"
                            class="rounded-xl"
                            readonly
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dialog_owner">{{ __('Owner') }}</Label>
                        <Input
                            id="dialog_owner"
                            :model-value="formOwner"
                            class="rounded-xl"
                            readonly
                        />
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <Button variant="outline" @click="activeDialog = null">
                        {{ __('Cancel') }}
                    </Button>
                    <Button @click="activeDialog = null">
                        {{ __('Save changes') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Sheet v-model:open="isSheetOpen">
            <SheetContent side="right" class="sm:max-w-md">
                <SheetHeader class="space-y-2">
                    <SheetTitle>{{ __('Filter sheet example') }}</SheetTitle>
                    <SheetDescription>
                        {{
                            __(
                                'Use Sheets for dashboard filters, appearance controls, and other secondary workflows.',
                            )
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="space-y-4 px-6 py-6">
                    <div
                        class="rounded-2xl border border-sidebar-border/70 bg-sidebar/20 p-4 text-sm leading-6 text-muted-foreground"
                    >
                        {{
                            __(
                                'This surface intentionally stays separate from create and edit actions so CRUD flows remain dialog-based.',
                            )
                        }}
                    </div>
                    <div class="space-y-2">
                        <Label for="sheet_focus">{{ __('Focus area') }}</Label>
                        <Input
                            id="sheet_focus"
                            :model-value="
                                __('Filter controls and preview states')
                            "
                            class="rounded-xl"
                            readonly
                        />
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
