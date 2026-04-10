<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/translations';
import { sync as syncPermissions } from '@/routes/tyanc/permissions';
import type { PermissionSyncSummary } from '@/types';

const props = defineProps<{
    summary: PermissionSyncSummary;
    canSync: boolean;
}>();

const { __ } = useTranslations();
const syncing = ref(false);

function runSync() {
    syncing.value = true;
    router.post(
        syncPermissions.url(),
        {},
        {
            preserveScroll: true,
            only: ['permissionsTable'],
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

function formatDate(iso: string | null): string {
    if (!iso) return __('Never');
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-4 rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3"
    >
        <!-- Stat pills -->
        <div class="flex flex-wrap items-center gap-3 text-xs">
            <span class="text-muted-foreground">
                {{ __('Total:') }}
                <strong class="text-foreground tabular-nums">{{
                    props.summary.total
                }}</strong>
            </span>
            <span class="text-emerald-700 dark:text-emerald-300">
                {{ __('Synced:') }}
                <strong class="tabular-nums">{{ props.summary.synced }}</strong>
            </span>
            <span
                v-if="props.summary.orphaned > 0"
                class="text-amber-700 dark:text-amber-300"
            >
                {{ __('Orphaned:') }}
                <strong class="tabular-nums">{{
                    props.summary.orphaned
                }}</strong>
            </span>
            <span
                v-if="props.summary.missing > 0"
                class="text-red-700 dark:text-red-300"
            >
                {{ __('Missing:') }}
                <strong class="tabular-nums">{{
                    props.summary.missing
                }}</strong>
            </span>
        </div>

        <span class="hidden text-muted-foreground/40 select-none sm:block"
            >|</span
        >

        <!-- Last synced -->
        <span class="text-xs text-muted-foreground">
            {{ __('Last sync:') }}
            {{ formatDate(props.summary.last_synced_at) }}
        </span>

        <!-- Sync button -->
        <div class="ml-auto">
            <Button
                v-if="props.canSync"
                variant="outline"
                size="sm"
                class="gap-2 text-xs"
                :disabled="syncing"
                @click="runSync"
            >
                <RefreshCw
                    class="size-3.5"
                    :class="{ 'animate-spin': syncing }"
                />
                {{ syncing ? __('Syncing…') : __('Sync permissions') }}
            </Button>
        </div>
    </div>
</template>
