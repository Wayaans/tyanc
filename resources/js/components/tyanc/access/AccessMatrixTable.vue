<script setup lang="ts">
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/lib/translations';
import type { AccessMatrixRow, RoleData } from '@/types';

const props = defineProps<{
    rows: AccessMatrixRow[];
    roles: RoleData[];
    updating?: boolean;
}>();

const emit = defineEmits<{
    toggle: [
        payload: { permissionId: number; roleId: number; granted: boolean },
    ];
}>();

const { __ } = useTranslations();

type ResourceGroup = {
    resource: string | null;
    rows: AccessMatrixRow[];
};

type AppGroup = {
    app: string;
    appLabel: string | null;
    resources: ResourceGroup[];
    totalRows: number;
};

const groupedByApp = computed<AppGroup[]>(() => {
    const appMap = new Map<string, Map<string, AccessMatrixRow[]>>();
    const appLabelMap = new Map<string, string | null>();

    for (const row of props.rows) {
        const appKey = row.app ?? __('Global');
        const resourceKey = row.resource ?? '';

        if (!appLabelMap.has(appKey)) {
            appLabelMap.set(appKey, row.app_label ?? null);
        }

        if (!appMap.has(appKey)) {
            appMap.set(appKey, new Map());
        }

        const resourceMap = appMap.get(appKey)!;

        if (!resourceMap.has(resourceKey)) {
            resourceMap.set(resourceKey, []);
        }

        resourceMap.get(resourceKey)!.push(row);
    }

    return Array.from(appMap.entries()).map(([app, resourceMap]) => ({
        app,
        appLabel: appLabelMap.get(app) ?? null,
        resources: Array.from(resourceMap.entries()).map(
            ([resource, rows]) => ({
                resource: resource || null,
                rows,
            }),
        ),
        totalRows: Array.from(resourceMap.values()).reduce(
            (acc, r) => acc + r.length,
            0,
        ),
    }));
});

// Collapsible app groups
const collapsedApps = ref<Set<string>>(new Set());

function toggleApp(app: string) {
    if (collapsedApps.value.has(app)) {
        collapsedApps.value.delete(app);
    } else {
        collapsedApps.value.add(app);
    }
}

function isCollapsed(app: string): boolean {
    return collapsedApps.value.has(app);
}

function isGranted(row: AccessMatrixRow, roleId: number): boolean {
    return Boolean(row[`role_${roleId}`]);
}

function handleToggle(
    row: AccessMatrixRow,
    roleId: number,
    currentlyGranted: boolean,
) {
    emit('toggle', {
        permissionId: row.id,
        roleId,
        granted: !currentlyGranted,
    });
}

/** Count total grants in an app group. */
function appGrantCount(group: AppGroup): number {
    let count = 0;
    for (const rg of group.resources) {
        for (const row of rg.rows) {
            for (const role of props.roles) {
                if (isGranted(row, role.id)) count++;
            }
        }
    }
    return count;
}

/** Derive a short action label from the full permission string. */
function actionLabel(row: AccessMatrixRow): string {
    if (row.action_label) {
        return row.action_label;
    }

    if (row.action) {
        return row.action;
    }

    // Fall back to last segment of the dotted permission name
    const parts = row.permission.split('.');

    return parts[parts.length - 1] ?? row.permission;
}
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-sidebar-border/70 bg-muted/30">
                    <!-- Sticky resource/permission column -->
                    <th
                        class="sticky left-0 z-10 bg-muted/30 px-4 py-3 text-left text-xs font-medium text-muted-foreground"
                    >
                        {{ __('Resource / Permission') }}
                    </th>
                    <th
                        v-for="role in props.roles"
                        :key="role.id"
                        class="min-w-[80px] px-3 py-3 text-center text-xs font-medium text-muted-foreground"
                    >
                        <div class="flex flex-col items-center gap-0.5">
                            <span class="max-w-[96px] truncate">{{
                                role.name
                            }}</span>
                            <Badge
                                variant="secondary"
                                class="rounded-full px-1.5 py-0 text-[10px] font-normal"
                            >
                                L{{ role.level }}
                            </Badge>
                        </div>
                    </th>
                </tr>
            </thead>

            <tbody>
                <template v-for="group in groupedByApp" :key="group.app">
                    <!-- App group header (collapsible) -->
                    <tr
                        class="cursor-pointer bg-sidebar/30 transition-colors hover:bg-sidebar/50"
                        @click="toggleApp(group.app)"
                    >
                        <td :colspan="props.roles.length + 1" class="px-4 py-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs text-muted-foreground/50 transition-transform duration-150 select-none"
                                        :class="{
                                            '-rotate-90': isCollapsed(
                                                group.app,
                                            ),
                                        }"
                                    >
                                        ▾
                                    </span>
                                    <Badge
                                        variant="outline"
                                        class="rounded-full font-mono text-xs"
                                    >
                                        {{ group.app }}
                                    </Badge>
                                    <span
                                        v-if="
                                            group.appLabel &&
                                            group.appLabel !== group.app
                                        "
                                        class="text-xs font-medium text-foreground/70"
                                    >
                                        {{ group.appLabel }}
                                    </span>
                                    <span
                                        class="text-xs text-muted-foreground/50 tabular-nums"
                                    >
                                        ({{ group.totalRows }})
                                    </span>
                                </div>
                                <span
                                    v-if="appGrantCount(group) > 0"
                                    class="pr-1 text-xs text-muted-foreground/50 tabular-nums"
                                >
                                    {{ appGrantCount(group) }}
                                    {{ __('grants') }}
                                </span>
                            </div>
                        </td>
                    </tr>

                    <template v-if="!isCollapsed(group.app)">
                        <template
                            v-for="resourceGroup in group.resources"
                            :key="resourceGroup.resource ?? '_global_'"
                        >
                            <!-- Resource sub-header -->
                            <tr
                                class="border-t border-sidebar-border/40 bg-muted/5"
                            >
                                <td
                                    :colspan="props.roles.length + 1"
                                    class="px-4 py-2 pl-8"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="h-3 w-0.5 rounded-full bg-border"
                                        />
                                        <span
                                            class="text-xs font-semibold tracking-wide text-foreground/80 uppercase"
                                        >
                                            {{
                                                resourceGroup.rows[0]
                                                    ?.resource_label ??
                                                resourceGroup.resource ??
                                                __('General')
                                            }}
                                        </span>
                                        <span
                                            class="text-[10px] text-muted-foreground/50 tabular-nums"
                                        >
                                            ({{ resourceGroup.rows.length }})
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Permission rows -->
                            <tr
                                v-for="row in resourceGroup.rows"
                                :key="row.id"
                                class="border-t border-sidebar-border/20 transition-colors hover:bg-muted/20"
                            >
                                <!-- Sticky permission name cell -->
                                <td
                                    class="sticky left-0 z-10 bg-background px-4 py-2.5 pl-10"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <!-- Action as primary label -->
                                        <span
                                            class="inline-flex items-center rounded bg-secondary px-1.5 py-0.5 text-xs font-semibold text-secondary-foreground"
                                        >
                                            {{ actionLabel(row) }}
                                        </span>
                                        <!-- Page context if present -->
                                        <span
                                            v-if="row.page"
                                            class="text-[10px] text-muted-foreground/60"
                                        >
                                            {{ row.page }}
                                        </span>
                                        <!-- Full permission string as secondary -->
                                        <span
                                            class="block w-full font-mono text-[10px] leading-none text-muted-foreground/40"
                                        >
                                            {{ row.permission }}
                                        </span>
                                    </div>
                                </td>

                                <td
                                    v-for="role in props.roles"
                                    :key="role.id"
                                    class="px-3 py-2.5 text-center"
                                >
                                    <Checkbox
                                        :checked="isGranted(row, role.id)"
                                        :disabled="props.updating"
                                        @update:checked="
                                            handleToggle(
                                                row,
                                                role.id,
                                                isGranted(row, role.id),
                                            )
                                        "
                                    />
                                </td>
                            </tr>
                        </template>
                    </template>
                </template>
            </tbody>
        </table>

        <div
            v-if="props.rows.length === 0"
            class="flex flex-col items-center justify-center gap-2 py-12 text-center"
        >
            <p class="text-sm font-medium text-foreground">
                {{ __('No permissions to display.') }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ __('Adjust the filters or sync permissions first.') }}
            </p>
        </div>
    </div>
</template>
