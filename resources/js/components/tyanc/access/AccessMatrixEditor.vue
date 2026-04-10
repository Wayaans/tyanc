<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/lib/translations';
import type { AccessMatrixRow, RoleData } from '@/types';

const props = defineProps<{
    rows: AccessMatrixRow[];
    role: RoleData;
    updating?: boolean;
}>();

const emit = defineEmits<{
    toggle: [
        payload: { permissionId: number; roleId: number; granted: boolean },
    ];
}>();

const { __ } = useTranslations();

// ─── Types ───────────────────────────────────────────────────────────────────

type ResourceGroup = {
    resourceKey: string;
    resourceLabel: string;
    rows: AccessMatrixRow[];
};

// ─── Grouped data ─────────────────────────────────────────────────────────────

const resourceGroups = computed<ResourceGroup[]>(() => {
    const map = new Map<string, AccessMatrixRow[]>();

    for (const row of props.rows) {
        const key = row.resource ?? '';
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(row);
    }

    return Array.from(map.entries()).map(([key, rows]) => ({
        resourceKey: key,
        resourceLabel:
            rows[0]?.resource_label ?? rows[0]?.resource ?? __('General'),
        rows,
    }));
});

// ─── Resource selection ───────────────────────────────────────────────────────

const selectedResourceKey = ref<string | null>(null);

// Whenever the available resource groups change (different app selected),
// reset to the first available resource.
watch(
    resourceGroups,
    (groups) => {
        const stillValid =
            selectedResourceKey.value !== null &&
            groups.some((g) => g.resourceKey === selectedResourceKey.value);

        if (!stillValid) {
            selectedResourceKey.value = groups[0]?.resourceKey ?? null;
        }
    },
    { immediate: true },
);

const selectedGroup = computed<ResourceGroup | null>(
    () =>
        resourceGroups.value.find(
            (g) => g.resourceKey === selectedResourceKey.value,
        ) ?? null,
);

// ─── Permission helpers ───────────────────────────────────────────────────────

function isGranted(row: AccessMatrixRow): boolean {
    return Boolean(row[`role_${props.role.id}`]);
}

function actionLabel(row: AccessMatrixRow): string {
    if (row.action_label) return row.action_label;
    if (row.action) return row.action;
    const parts = row.permission.split('.');
    return parts[parts.length - 1] ?? row.permission;
}

function handleToggle(row: AccessMatrixRow) {
    emit('toggle', {
        permissionId: row.id,
        roleId: props.role.id,
        granted: !isGranted(row),
    });
}

// ─── Grant counts ─────────────────────────────────────────────────────────────

function grantCountForGroup(group: ResourceGroup): number {
    return group.rows.filter(isGranted).length;
}

const totalGrants = computed(
    () => props.rows.filter((row) => isGranted(row)).length,
);
</script>

<template>
    <div
        class="flex min-h-[420px] overflow-hidden rounded-xl border border-sidebar-border/70"
    >
        <!-- ── Left: Resource list ────────────────────────────────────────── -->
        <aside
            class="flex w-52 shrink-0 flex-col border-r border-sidebar-border/70 bg-muted/20"
        >
            <!-- Sidebar header -->
            <div
                class="flex items-center justify-between border-b border-sidebar-border/40 px-3 py-2.5"
            >
                <p
                    class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ __('Resources') }}
                </p>
                <span
                    v-if="totalGrants > 0"
                    class="font-mono text-[10px] text-muted-foreground/50 tabular-nums"
                >
                    {{ totalGrants }} {{ __('granted') }}
                </span>
            </div>

            <!-- Resource items -->
            <nav class="flex-1 overflow-y-auto p-2">
                <button
                    v-for="group in resourceGroups"
                    :key="group.resourceKey"
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors"
                    :class="
                        selectedResourceKey === group.resourceKey
                            ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                            : 'text-foreground/70 hover:bg-muted/50 hover:text-foreground'
                    "
                    @click="selectedResourceKey = group.resourceKey"
                >
                    <span class="truncate">{{ group.resourceLabel }}</span>
                    <div class="ml-2 flex shrink-0 items-center gap-1">
                        <!-- Granted count dot -->
                        <span
                            v-if="grantCountForGroup(group) > 0"
                            class="flex size-4 items-center justify-center rounded-full bg-primary/15 font-mono text-[9px] text-primary tabular-nums"
                        >
                            {{ grantCountForGroup(group) }}
                        </span>
                        <Badge
                            variant="secondary"
                            class="rounded-full px-1.5 py-0 text-[10px] font-normal"
                        >
                            {{ group.rows.length }}
                        </Badge>
                    </div>
                </button>

                <div
                    v-if="resourceGroups.length === 0"
                    class="px-3 py-4 text-center text-xs text-muted-foreground"
                >
                    {{ __('No resources found.') }}
                </div>
            </nav>
        </aside>

        <!-- ── Right: Permission editor ──────────────────────────────────── -->
        <section class="flex min-w-0 flex-1 flex-col">
            <template v-if="selectedGroup">
                <!-- Resource header -->
                <div
                    class="flex items-center gap-3 border-b border-sidebar-border/40 px-5 py-3"
                >
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ selectedGroup.resourceLabel }}
                        </h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{
                                __('Permissions for :role', {
                                    role: props.role.name,
                                })
                            }}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        class="shrink-0 rounded-full text-xs"
                    >
                        L{{ props.role.level }}
                    </Badge>
                </div>

                <!-- Permission action chips -->
                <div class="flex-1 overflow-y-auto p-5">
                    <TooltipProvider :delay-duration="400">
                        <div class="flex flex-wrap gap-2">
                            <Tooltip
                                v-for="row in selectedGroup.rows"
                                :key="row.id"
                            >
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-xs font-medium transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        :class="
                                            isGranted(row)
                                                ? 'border-primary/25 bg-primary/10 text-primary hover:bg-primary/15'
                                                : 'border-border bg-background text-muted-foreground hover:border-muted-foreground/30 hover:bg-muted/30 hover:text-foreground'
                                        "
                                        :disabled="props.updating"
                                        @click="handleToggle(row)"
                                    >
                                        <Checkbox
                                            :checked="isGranted(row)"
                                            :disabled="props.updating"
                                            class="pointer-events-none size-3.5 shrink-0"
                                            tabindex="-1"
                                            aria-hidden="true"
                                        />
                                        <span>{{ actionLabel(row) }}</span>
                                        <span
                                            v-if="row.page"
                                            class="rounded bg-muted px-1 py-0.5 text-[10px] font-normal text-muted-foreground"
                                        >
                                            {{ row.page }}
                                        </span>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="bottom"
                                    class="font-mono text-xs"
                                >
                                    {{ row.permission }}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </TooltipProvider>

                    <div
                        class="mt-4 text-xs text-muted-foreground/50 tabular-nums"
                    >
                        {{ grantCountForGroup(selectedGroup) }} /
                        {{ selectedGroup.rows.length }}
                        {{ __('granted') }}
                    </div>
                </div>
            </template>

            <!-- Empty: no resource selected (shouldn't normally show) -->
            <div
                v-else
                class="flex flex-1 items-center justify-center p-10 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    {{
                        __('Select a resource on the left to view permissions.')
                    }}
                </p>
            </div>
        </section>
    </div>
</template>
