<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AccessMatrixFilterBar from '@/components/tyanc/access/AccessMatrixFilterBar.vue';
import AccessMatrixTable from '@/components/tyanc/access/AccessMatrixTable.vue';
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

const filters = ref({ role: '', app: '' });

const visibleRoles = computed(() => {
    if (!filters.value.role) {
        return props.accessMatrix.roles;
    }

    return props.accessMatrix.roles.filter(
        (r) => String(r.id) === filters.value.role,
    );
});

const visibleRows = computed(() => {
    let rows = props.accessMatrix.matrix.rows;

    if (filters.value.app) {
        rows = rows.filter((r) => r.app === filters.value.app);
    }

    return rows;
});

function handleToggle(payload: {
    permissionId: number;
    roleId: number;
    granted: boolean;
}) {
    updating.value = true;

    router.patch(
        updateMatrix.url(),
        {
            permission_id: payload.permissionId,
            role_id: payload.roleId,
            granted: payload.granted,
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
                                'Visualise and manage permission grants across roles.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    @click="openPreview"
                >
                    <Eye class="size-4" />
                    {{ __('Preview access') }}
                </Button>
            </div>

            <!-- Filter bar -->
            <AccessMatrixFilterBar
                v-model="filters"
                :roles="props.accessMatrix.roles"
                :apps="props.accessMatrix.apps"
            />

            <!-- Matrix table -->
            <AccessMatrixTable
                :rows="visibleRows"
                :roles="visibleRoles"
                :updating="updating"
                @toggle="handleToggle"
            />

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
