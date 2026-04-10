<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import AppForm, {
    type AppFormFields,
} from '@/components/tyanc/apps/AppForm.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index, store } from '@/routes/tyanc/apps';

const { __ } = useTranslations();
const { appsCreateBreadcrumbs } = useAppNavigation();

const breadcrumbs = appsCreateBreadcrumbs;

const defaultForm = (): AppFormFields => ({
    key: '',
    label: '',
    route_prefix: '',
    icon: 'layout-grid',
    permission_namespace: '',
    enabled: true,
    sort_order: 0,
    pages: [],
});

const form = ref<AppFormFields>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

function goBack() {
    router.visit(index.url());
}

function submit() {
    processing.value = true;
    errors.value = {};

    router.post(store.url(), form.value, {
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
    <Head :title="__('New app')" />

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
                <div class="space-y-0.5">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('New app') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Register a new application and define its managed pages.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Form card -->
            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
            >
                <form class="space-y-6 p-6 md:p-8" @submit.prevent="submit">
                    <AppForm v-model="form" :errors="errors" />

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
                            {{ __('Create app') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
