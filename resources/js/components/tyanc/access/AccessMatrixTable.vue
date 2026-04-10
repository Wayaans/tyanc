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

type GroupedResource = {
    resource: string | null;
    rows: AccessMatrixRow[];
};

type GroupedApp = {
    app: string;
    resources: GroupedResource[];
    totalRows: number;
};

const groupedByApp = computed<GroupedApp[]>(() => {
    // Build app → resource → rows hierarchy
    const appMap = new Map<string, Map<string, AccessMatrixRow[]>>();

    for (const row of props.rows) {
        const appKey = row.app ?? __('Global');
        const resourceKey = row.resource ?? '';

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

/** Count granted permissions across all roles for an app group. */
function appGrantCount(group: GroupedApp): number {
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
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-sidebar-border/70 bg-muted/30">
                    <th
                        class="px-4 py-3 text-left text-xs font-medium text-muted-foreground"
                    >
                        {{ __('Permission') }}
                    </th>
                    <th
                        v-for="role in props.roles"
                        :key="role.id"
                        class="px-3 py-3 text-center text-xs font-medium text-muted-foreground"
                    >
                        <div class="flex flex-col items-center gap-0.5">
                            <span>{{ role.name }}</span>
                            <span class="text-muted-foreground/60">
                                {{ __('L:n', { n: String(role.level) }) }}
                            </span>
                        </div>
                    </th>
                </tr>
            </thead>

            <tbody>
                <template v-for="group in groupedByApp" :key="group.app">
                    <!-- App group header (collapsible) -->
                    <tr
                        class="cursor-pointer bg-sidebar/20 transition-colors hover:bg-sidebar/30"
                        @click="toggleApp(group.app)"
                    >
                        <td
                            :colspan="props.roles.length + 1"
                            class="px-4 py-2.5"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs text-muted-foreground/60 transition-transform select-none"
                                        :class="{
                                            'rotate-[-90deg]': isCollapsed(
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
                                        class="text-xs text-muted-foreground/60 tabular-nums"
                                    >
                                        {{ group.totalRows }}
                                        {{
                                            group.totalRows === 1
                                                ? __('permission')
                                                : __('permissions')
                                        }}
                                    </span>
                                </div>
                                <span
                                    v-if="appGrantCount(group) > 0"
                                    class="pr-1 text-xs text-muted-foreground/60 tabular-nums"
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
                            <!-- Resource sub-header (when resource is named) -->
                            <tr
                                v-if="resourceGroup.resource"
                                class="border-t border-sidebar-border/30 bg-muted/10"
                            >
                                <td
                                    :colspan="props.roles.length + 1"
                                    class="px-6 py-1.5"
                                >
                                    <span
                                        class="text-xs font-medium tracking-wide text-muted-foreground/70 uppercase"
                                    >
                                        {{ resourceGroup.resource }}
                                    </span>
                                </td>
                            </tr>

                            <!-- Permission rows -->
                            <tr
                                v-for="row in resourceGroup.rows"
                                :key="row.id"
                                class="border-t border-sidebar-border/30 transition-colors hover:bg-muted/20"
                            >
                                <td class="px-4 py-2.5 pl-6">
                                    <div class="space-y-1">
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                class="font-mono text-xs text-foreground"
                                            >
                                                {{ row.permission }}
                                            </span>
                                            <span
                                                v-if="row.action"
                                                class="inline-flex items-center rounded bg-secondary px-1.5 py-0.5 text-xs font-medium text-secondary-foreground"
                                            >
                                                {{ row.action }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <span v-if="row.page">{{
                                                __('Page: :page', {
                                                    page: String(row.page),
                                                })
                                            }}</span>
                                            <span v-if="row.resource">{{
                                                __('Resource: :resource', {
                                                    resource: String(
                                                        row.resource,
                                                    ),
                                                })
                                            }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    v-for="role in props.roles"
                                    :key="role.id"
                                    class="px-3 py-2.5 text-center"
                                >
                                    <Checkbox
                                        :checked="isGranted(row, role.id)"
                                        :disabled="
                                            props.updating || role.is_reserved
                                        "
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
