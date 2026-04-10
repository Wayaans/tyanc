<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { Eye, ShieldAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AccessMatrixEditor from '@/components/tyanc/access/AccessMatrixEditor.vue';
import AccessMatrixFilterBar from '@/components/tyanc/access/AccessMatrixFilterBar.vue';
import CmsPageVisibilityCard from '@/components/tyanc/access/CmsPageVisibilityCard.vue';
import EffectiveAccessPreviewSheet from '@/components/tyanc/access/EffectiveAccessPreviewSheet.vue';
import NavigationVisibilityLegend from '@/components/tyanc/access/NavigationVisibilityLegend.vue';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index, update as updateMatrix } from '@/routes/tyanc/access-matrix';
import type { AccessMatrixPayload } from '@/types';

const props = defineProps<{
    accessMatrix: AccessMatrixPayload;
}>();

const { __ } = useTranslations();
const { accessMatrixBreadcrumbs } = useAppNavigation();

const breadcrumbs = accessMatrixBreadcrumbs;

const previewSheetOpen = ref(false);
const updating = ref(false);

const filters = computed(() => ({
    role: props.accessMatrix.selected_role_id
        ? String(props.accessMatrix.selected_role_id)
        : '',
    app: props.accessMatrix.selected_app_key ?? '',
}));

const selectedRole = computed(
    () =>
        props.accessMatrix.roles.find(
            (role) => role.id === props.accessMatrix.selected_role_id,
        ) ?? null,
);

const selectedApp = computed(
    () =>
        props.accessMatrix.apps.find(
            (app) => app.key === props.accessMatrix.selected_app_key,
        ) ?? null,
);

const editorReady = computed(
    () => selectedRole.value !== null && selectedApp.value !== null,
);

const selectionQuery = computed(() => ({
    role_id: selectedRole.value?.id,
    app: selectedApp.value?.key,
}));

// ─── Toggle handler ───────────────────────────────────────────────────────────

function updateFilters(next: { role: string; app: string }) {
    router.get(
        index.url({
            query: {
                role_id: next.role || undefined,
                app: next.app || undefined,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['accessMatrix'],
        },
    );
}

function handleToggle(payload: {
    permissionId: number;
    roleId: number;
    granted: boolean;
}) {
    updating.value = true;

    router.patch(
        updateMatrix.url({ query: selectionQuery.value }),
        {
            permission_id: payload.permissionId,
            role_id: payload.roleId,
            granted: payload.granted,
            app: selectedApp.value?.key,
        },
        {
            preserveScroll: true,
            only: ['accessMatrix'],
            onFinish: () => {
                updating.value = false;
            },
        },
    );
}

function openPreview() {
    previewSheetOpen.value = true;
}
</script>

<template>
    <Head :title="__('Access Matrix')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Access matrix') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Assign permissions to a role, scoped to a single app.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    :disabled="selectedRole === null"
                    @click="openPreview"
                >
                    <Eye class="size-4" />
                    {{ __('Preview access') }}
                </Button>
            </div>

            <!-- Filter bar — single-select role + single-select app -->
            <AccessMatrixFilterBar
                :model-value="filters"
                :roles="props.accessMatrix.roles"
                :apps="props.accessMatrix.apps"
                @update:model-value="updateFilters"
            />

            <!-- Role-scoped permission editor -->
            <AccessMatrixEditor
                v-if="editorReady"
                :rows="props.accessMatrix.matrix.rows"
                :role="selectedRole || props.accessMatrix.roles[0]"
                :updating="updating"
                @toggle="handleToggle"
            />

            <!-- Prompt shown before both selections are made -->
            <div
                v-else
                class="flex min-h-[300px] flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 bg-muted/10 p-10 text-center"
            >
                <ShieldAlert class="size-8 text-muted-foreground/40" />
                <div class="space-y-1">
                    <p class="text-sm font-medium text-foreground/70">
                        {{ __('No selection yet') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            __(
                                'Choose a role and an app above to start editing permissions.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Side info cards -->
            <div class="grid gap-4 lg:grid-cols-2">
                <NavigationVisibilityLegend />
                <CmsPageVisibilityCard :roles="props.accessMatrix.roles" />
            </div>
        </div>
    </AppLayout>

    <EffectiveAccessPreviewSheet
        v-model:open="previewSheetOpen"
        :preview="props.accessMatrix.effective_preview"
    />
</template>
