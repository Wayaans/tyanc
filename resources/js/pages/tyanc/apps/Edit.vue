<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import AppForm, {
    type AppFormFields,
    type AppPageForm,
} from '@/components/tyanc/apps/AppForm.vue';
import AppStatusBadge from '@/components/tyanc/apps/AppStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index, update } from '@/routes/tyanc/apps';
import type { AppData } from '@/types';

const props = defineProps<{
    app: AppData;
}>();

const { __ } = useTranslations();
const { appsEditBreadcrumbs } = useAppNavigation();

const breadcrumbs = appsEditBreadcrumbs(props.app.label, props.app.key);

function fromAppData(app: AppData): AppFormFields {
    return {
        key: app.key,
        label: app.label,
        route_prefix: app.route_prefix,
        icon: app.icon,
        permission_namespace: app.permission_namespace,
        enabled: app.enabled,
        sort_order: app.sort_order,
        pages: app.pages.map(
            (page): AppPageForm => ({
                key: page.key,
                label: page.label,
                route_name: page.route_name ?? '',
                path: page.path ?? '',
                permission_name: page.permission_name ?? '',
                sort_order: page.sort_order,
                enabled: page.enabled,
                is_navigation: page.is_navigation,
                is_system: page.is_system,
            }),
        ),
    };
}

const form = ref<AppFormFields>(fromAppData(props.app));
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

function goBack() {
    router.visit(index.url());
}

function submit() {
    processing.value = true;
    errors.value = {};

    router.patch(update.url({ app: props.app.key }), form.value, {
        preserveScroll: true,
        onError: (responseErrors) => {
            errors.value = responseErrors as Partial<Record<string, string>>;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="__('Edit :label', { label: props.app.label })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <!-- Page header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    :aria-label="__('Back to apps')"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" />
                </Button>
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="min-w-0 space-y-0.5">
                        <div class="flex items-center gap-2">
                            <h1
                                class="truncate text-xl font-semibold tracking-tight text-foreground"
                            >
                                {{
                                    __('Edit :label', {
                                        label: props.app.label,
                                    })
                                }}
                            </h1>
                            <Badge
                                v-if="props.app.is_system"
                                variant="outline"
                                class="rounded-full text-xs text-muted-foreground"
                            >
                                {{ __('Protected') }}
                            </Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <p
                                class="truncate font-mono text-xs text-muted-foreground"
                            >
                                {{ props.app.key }}
                            </p>
                            <AppStatusBadge
                                :enabled="props.app.enabled"
                                :is-system="props.app.is_system"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form card -->
            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
            >
                <form class="space-y-6 p-6 md:p-8" @submit.prevent="submit">
                    <AppForm
                        v-model="form"
                        :errors="errors"
                        :is-system="props.app.is_system"
                    />

                    <Separator />

                    <div class="flex items-center justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="goBack"
                        >
                            {{ __('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            {{ __('Save changes') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
