<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { sync } from '@/routes/cumpu/approval-rules';
import type { ApprovalRuleSyncState, ManagedApprovalRule } from '@/types/cumpu';

const props = defineProps<{
    rules: ManagedApprovalRule[];
    canManage: boolean;
    status?: string | null;
}>();

const { __ } = useTranslations();

const syncing = ref(false);

const totalCount = computed(() => props.rules.length);

const syncedCount = computed(
    () => props.rules.filter((r) => r.sync_state === 'synced').length,
);

const pendingCount = computed(
    () => props.rules.filter((r) => r.sync_state === 'pending_sync').length,
);

const incompleteCount = computed(
    () => props.rules.filter((r) => r.sync_state === 'incomplete').length,
);

const retiredCount = computed(
    () => props.rules.filter((r) => r.sync_state === 'removed').length,
);

const allSynced = computed(
    () =>
        pendingCount.value === 0 &&
        incompleteCount.value === 0 &&
        totalCount.value > 0,
);

const syncStateLabel = (state: ApprovalRuleSyncState): string => {
    const labels: Record<ApprovalRuleSyncState, string> = {
        synced: __('Synced'),
        incomplete: __('Needs setup'),
        pending_sync: __('Pending sync'),
        removed: __('Removed'),
        unknown: __('Unknown'),
    };

    return labels[state] ?? state;
};

function doSync() {
    syncing.value = true;

    router.post(
        sync.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

defineExpose({ syncStateLabel });
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
    >
        <div class="flex flex-wrap items-center justify-between gap-4 p-5">
            <div class="flex items-start gap-3">
                <!-- Status icon -->
                <div
                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                    :class="
                        allSynced
                            ? 'bg-emerald-100 dark:bg-emerald-500/15'
                            : 'bg-amber-100 dark:bg-amber-500/15'
                    "
                >
                    <CheckCircle2
                        v-if="allSynced"
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                    <AlertCircle
                        v-else
                        class="size-4 text-amber-600 dark:text-amber-400"
                    />
                </div>

                <!-- Summary -->
                <div class="space-y-1">
                    <p class="text-sm font-medium text-foreground">
                        <template v-if="allSynced">
                            {{ __('All capabilities synced') }}
                        </template>
                        <template v-else-if="pendingCount > 0">
                            {{
                                __(':n pending sync', {
                                    n: String(pendingCount),
                                })
                            }}
                        </template>
                        <template v-else-if="incompleteCount > 0">
                            {{
                                __(':n need setup', {
                                    n: String(incompleteCount),
                                })
                            }}
                        </template>
                        <template v-else>
                            {{
                                __(':n retired', {
                                    n: String(retiredCount),
                                })
                            }}
                        </template>
                    </p>

                    <div
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span>
                            {{
                                __(':total total · :synced synced', {
                                    total: String(totalCount),
                                    synced: String(syncedCount),
                                })
                            }}
                        </span>

                        <span v-if="incompleteCount > 0">
                            ·
                            {{
                                __(':count incomplete', {
                                    count: String(incompleteCount),
                                })
                            }}
                        </span>

                        <span v-if="retiredCount > 0">
                            · {{ retiredCount }} {{ __('retired') }}
                        </span>
                    </div>

                    <!-- Flash status from last sync -->
                    <p
                        v-if="props.status"
                        class="text-xs text-emerald-700 dark:text-emerald-400"
                    >
                        {{ props.status }}
                    </p>
                </div>
            </div>

            <!-- Sync action -->
            <Button
                v-if="props.canManage"
                variant="outline"
                size="sm"
                :disabled="syncing"
                @click="doSync"
            >
                <Spinner v-if="syncing" />
                <RefreshCw v-else class="size-3.5" />
                {{ __('Sync capabilities') }}
            </Button>
        </div>

        <!-- Pending sync detail -->
        <div
            v-if="pendingCount > 0 || incompleteCount > 0"
            class="border-t border-sidebar-border/50 bg-amber-50/30 px-5 py-3 dark:bg-amber-500/[0.04]"
        >
            <p class="text-xs text-amber-800/80 dark:text-amber-300/70">
                <template v-if="pendingCount > 0">
                    {{
                        __(
                            'Run sync to register new capabilities from the source of truth config.',
                        )
                    }}
                </template>
                <template v-else>
                    {{
                        __(
                            'Some synced capabilities still need runtime workflow settings before they can be enabled.',
                        )
                    }}
                </template>
            </p>
        </div>
    </div>
</template>
