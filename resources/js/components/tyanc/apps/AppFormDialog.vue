<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { PlusCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import AppPermissionNamespaceField from '@/components/tyanc/apps/AppPermissionNamespaceField.vue';
import AppRoutePrefixField from '@/components/tyanc/apps/AppRoutePrefixField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { store, update } from '@/routes/tyanc/apps';
import type { AppData, AppPageData, AppRow } from '@/types';

type AppPageForm = {
    key: string;
    label: string;
    route_name: string;
    path: string;
    permission_name: string;
    sort_order: number;
    enabled: boolean;
    is_navigation: boolean;
    is_system: boolean;
};

type AppFormFields = {
    key: string;
    label: string;
    route_prefix: string;
    icon: string;
    permission_namespace: string;
    enabled: boolean;
    sort_order: number;
    pages: AppPageForm[];
};

const props = defineProps<{
    open: boolean;
    editingApp?: AppRow | null;
    apps?: AppData[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { __ } = useTranslations();

function defaultPage(sortOrder = 0): AppPageForm {
    return {
        key: '',
        label: '',
        route_name: '',
        path: '',
        permission_name: '',
        sort_order: sortOrder,
        enabled: true,
        is_navigation: true,
        is_system: false,
    };
}

function defaultForm(): AppFormFields {
    return {
        key: '',
        label: '',
        route_prefix: '',
        icon: 'layout-grid',
        permission_namespace: '',
        enabled: true,
        sort_order: 0,
        pages: [],
    };
}

function pageFormFromData(page: AppPageData): AppPageForm {
    return {
        key: page.key,
        label: page.label,
        route_name: page.route_name ?? '',
        path: page.path ?? '',
        permission_name: page.permission_name ?? '',
        sort_order: page.sort_order,
        enabled: page.enabled,
        is_navigation: page.is_navigation,
        is_system: page.is_system,
    };
}

const form = ref<AppFormFields>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

const isEditing = computed(() => Boolean(props.editingApp));
const isSystemApp = computed(() => Boolean(props.editingApp?.is_system));
const title = computed(() =>
    isEditing.value ? __('Edit app') : __('New app'),
);

const editingAppData = computed<AppData | null>(() => {
    if (!props.editingApp || !props.apps) {
        return null;
    }

    return props.apps.find((app) => app.key === props.editingApp?.key) ?? null;
});

watch(
    [() => props.editingApp, editingAppData],
    ([app, fullApp]) => {
        if (app) {
            form.value = {
                key: app.key,
                label: app.label,
                route_prefix: app.route_prefix,
                icon: app.icon,
                permission_namespace: app.permission_namespace,
                enabled: app.enabled,
                sort_order: app.sort_order,
                pages: fullApp?.pages.map(pageFormFromData) ?? [],
            };
        } else {
            form.value = defaultForm();
        }

        errors.value = {};
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function addPage() {
    form.value.pages.push(defaultPage(form.value.pages.length));
}

function removePage(index: number) {
    form.value.pages.splice(index, 1);
}

function submit() {
    processing.value = true;
    errors.value = {};

    const isEdit = isEditing.value && props.editingApp;
    const url = isEdit
        ? update.url({ app: props.editingApp!.key })
        : store.url();
    const method = isEdit ? 'patch' : 'post';

    router[method](url, form.value, {
        preserveScroll: true,
        onSuccess: () => close(),
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
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[92vh] max-w-3xl overflow-hidden">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    {{ title }}
                    <Badge
                        v-if="isSystemApp"
                        variant="outline"
                        class="rounded-full text-xs text-muted-foreground"
                    >
                        {{ __('Protected') }}
                    </Badge>
                </DialogTitle>
                <DialogDescription>
                    {{
                        isSystemApp
                            ? __(
                                  'System apps keep their identity and route metadata locked.',
                              )
                            : isEditing
                              ? __(
                                    'Update the app registry metadata and managed pages.',
                                )
                              : __(
                                    'Register a new application and define its managed pages.',
                                )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form
                class="space-y-5 overflow-y-auto pr-1"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="app-key">{{ __('App key') }}</Label>
                        <Input
                            id="app-key"
                            type="text"
                            placeholder="my-app"
                            :model-value="form.key"
                            :disabled="isSystemApp"
                            @update:model-value="form.key = String($event)"
                        />
                        <InputError :message="errors.key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="app-label">{{ __('Display name') }}</Label>
                        <Input
                            id="app-label"
                            type="text"
                            :placeholder="__('My App')"
                            :model-value="form.label"
                            @update:model-value="form.label = String($event)"
                        />
                        <InputError :message="errors.label" />
                    </div>
                </div>

                <AppRoutePrefixField
                    v-model="form.route_prefix"
                    :disabled="isSystemApp"
                    :error="errors.route_prefix"
                />

                <AppPermissionNamespaceField
                    v-model="form.permission_namespace"
                    :disabled="isSystemApp"
                    :error="errors.permission_namespace"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="app-icon">{{ __('Icon') }}</Label>
                        <Input
                            id="app-icon"
                            type="text"
                            placeholder="layout-grid"
                            :model-value="form.icon"
                            @update:model-value="form.icon = String($event)"
                        />
                        <InputError :message="errors.icon" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="app-sort-order">{{
                            __('Sort order')
                        }}</Label>
                        <Input
                            id="app-sort-order"
                            type="number"
                            min="0"
                            :model-value="String(form.sort_order)"
                            @update:model-value="
                                form.sort_order = Number($event)
                            "
                        />
                        <InputError :message="errors.sort_order" />
                    </div>
                </div>

                <label
                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2.5"
                >
                    <Checkbox
                        :checked="form.enabled"
                        :disabled="isSystemApp"
                        @update:checked="form.enabled = Boolean($event)"
                    />
                    <div>
                        <p class="text-sm font-medium">{{ __('Enabled') }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                __(
                                    'Show this app in navigation and allow direct access.',
                                )
                            }}
                        </p>
                    </div>
                </label>

                <Separator />

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">
                                {{ __('Pages') }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    __(
                                        'Define route-aware modules or pages for this app registry entry.',
                                    )
                                }}
                            </p>
                        </div>

                        <Button
                            v-if="!isSystemApp"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="gap-2"
                            @click="addPage"
                        >
                            <PlusCircle class="size-4" />
                            {{ __('Add page') }}
                        </Button>
                    </div>

                    <div
                        v-if="form.pages.length === 0"
                        class="rounded-lg border border-dashed border-sidebar-border/70 px-4 py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ __('No pages configured yet.') }}
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="(page, index) in form.pages"
                            :key="`${page.key || 'page'}-${index}`"
                            class="space-y-4 rounded-xl border border-sidebar-border/70 p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        class="rounded-full text-xs"
                                    >
                                        {{
                                            __('Page :n', {
                                                n: String(index + 1),
                                            })
                                        }}
                                    </Badge>
                                    <Badge
                                        v-if="page.is_system"
                                        variant="outline"
                                        class="rounded-full text-xs text-muted-foreground"
                                    >
                                        {{ __('System') }}
                                    </Badge>
                                </div>

                                <Button
                                    v-if="!isSystemApp && !page.is_system"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 text-muted-foreground hover:text-destructive"
                                    @click="removePage(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`page-key-${index}`">{{
                                        __('Page key')
                                    }}</Label>
                                    <Input
                                        :id="`page-key-${index}`"
                                        :model-value="page.key"
                                        :disabled="
                                            isSystemApp || page.is_system
                                        "
                                        @update:model-value="
                                            page.key = String($event)
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`page-label-${index}`">{{
                                        __('Label')
                                    }}</Label>
                                    <Input
                                        :id="`page-label-${index}`"
                                        :model-value="page.label"
                                        :disabled="
                                            isSystemApp && page.is_system
                                        "
                                        @update:model-value="
                                            page.label = String($event)
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`page-route-${index}`">{{
                                        __('Route name')
                                    }}</Label>
                                    <Input
                                        :id="`page-route-${index}`"
                                        :placeholder="__('demo.dashboard')"
                                        :model-value="page.route_name"
                                        :disabled="
                                            isSystemApp || page.is_system
                                        "
                                        @update:model-value="
                                            page.route_name = String($event)
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`page-path-${index}`">{{
                                        __('Path')
                                    }}</Label>
                                    <Input
                                        :id="`page-path-${index}`"
                                        :placeholder="__('/demo/dashboard')"
                                        :model-value="page.path"
                                        :disabled="
                                            isSystemApp || page.is_system
                                        "
                                        @update:model-value="
                                            page.path = String($event)
                                        "
                                    />
                                </div>

                                <div class="grid gap-2 sm:col-span-2">
                                    <Label :for="`page-permission-${index}`">{{
                                        __('Permission name')
                                    }}</Label>
                                    <Input
                                        :id="`page-permission-${index}`"
                                        :placeholder="
                                            __('demo.dashboard.viewany')
                                        "
                                        :model-value="page.permission_name"
                                        :disabled="
                                            isSystemApp || page.is_system
                                        "
                                        @update:model-value="
                                            page.permission_name =
                                                String($event)
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`page-sort-${index}`">{{
                                        __('Sort order')
                                    }}</Label>
                                    <Input
                                        :id="`page-sort-${index}`"
                                        type="number"
                                        min="0"
                                        :model-value="String(page.sort_order)"
                                        :disabled="
                                            isSystemApp || page.is_system
                                        "
                                        @update:model-value="
                                            page.sort_order = Number($event)
                                        "
                                    />
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label
                                    class="flex items-center gap-2 rounded-lg border border-sidebar-border/60 px-3 py-2"
                                >
                                    <Checkbox
                                        :checked="page.enabled"
                                        :disabled="
                                            isSystemApp || page.is_system
                                        "
                                        @update:checked="
                                            page.enabled = Boolean($event)
                                        "
                                    />
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ __('Enabled') }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                __(
                                                    'Allow this page to resolve in the registry.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </label>

                                <label
                                    class="flex items-center gap-2 rounded-lg border border-sidebar-border/60 px-3 py-2"
                                >
                                    <Checkbox
                                        :checked="page.is_navigation"
                                        :disabled="
                                            isSystemApp || page.is_system
                                        "
                                        @update:checked="
                                            page.is_navigation = Boolean($event)
                                        "
                                    />
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ __('Navigation entry') }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                __(
                                                    'Mark whether the page should appear in app navigation.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="close"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button :disabled="processing" @click="submit">
                    <Spinner v-if="processing" />
                    {{ isEditing ? __('Save changes') : __('Create app') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
