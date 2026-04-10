<script setup lang="ts">
import { PlusCircle, Trash2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import AppPermissionNamespaceField from '@/components/tyanc/apps/AppPermissionNamespaceField.vue';
import AppRoutePrefixField from '@/components/tyanc/apps/AppRoutePrefixField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/lib/translations';

export type AppPageForm = {
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

export type AppFormFields = {
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
    modelValue: AppFormFields;
    errors: Partial<Record<string, string>>;
    isSystem?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: AppFormFields];
}>();

const { __ } = useTranslations();

function update<K extends keyof AppFormFields>(
    key: K,
    value: AppFormFields[K],
) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function updatePage<K extends keyof AppPageForm>(
    index: number,
    key: K,
    value: AppPageForm[K],
) {
    const pages = props.modelValue.pages.map((p, i) =>
        i === index ? { ...p, [key]: value } : p,
    );
    update('pages', pages);
}

function addPage() {
    update('pages', [
        ...props.modelValue.pages,
        {
            key: '',
            label: '',
            route_name: '',
            path: '',
            permission_name: '',
            sort_order: props.modelValue.pages.length,
            enabled: true,
            is_navigation: true,
            is_system: false,
        },
    ]);
}

function removePage(index: number) {
    update(
        'pages',
        props.modelValue.pages.filter((_, i) => i !== index),
    );
}
</script>

<template>
    <div class="space-y-6">
        <!-- Identity -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="app-key">{{ __('App key') }}</Label>
                <Input
                    id="app-key"
                    type="text"
                    placeholder="my-app"
                    :model-value="props.modelValue.key"
                    :disabled="props.isSystem"
                    @update:model-value="update('key', String($event))"
                />
                <InputError :message="props.errors.key" />
            </div>

            <div class="grid gap-2">
                <Label for="app-label">{{ __('Display name') }}</Label>
                <Input
                    id="app-label"
                    type="text"
                    :placeholder="__('My App')"
                    :model-value="props.modelValue.label"
                    @update:model-value="update('label', String($event))"
                />
                <InputError :message="props.errors.label" />
            </div>
        </div>

        <AppRoutePrefixField
            :model-value="props.modelValue.route_prefix"
            :disabled="props.isSystem"
            :error="props.errors.route_prefix"
            @update:model-value="update('route_prefix', $event)"
        />

        <AppPermissionNamespaceField
            :model-value="props.modelValue.permission_namespace"
            :disabled="props.isSystem"
            :error="props.errors.permission_namespace"
            @update:model-value="update('permission_namespace', $event)"
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="app-icon">{{ __('Icon') }}</Label>
                <Input
                    id="app-icon"
                    type="text"
                    placeholder="layout-grid"
                    :model-value="props.modelValue.icon"
                    @update:model-value="update('icon', String($event))"
                />
                <InputError :message="props.errors.icon" />
            </div>

            <div class="grid gap-2">
                <Label for="app-sort-order">{{ __('Sort order') }}</Label>
                <Input
                    id="app-sort-order"
                    type="number"
                    min="0"
                    :model-value="String(props.modelValue.sort_order)"
                    @update:model-value="update('sort_order', Number($event))"
                />
                <InputError :message="props.errors.sort_order" />
            </div>
        </div>

        <label
            class="flex cursor-pointer items-center gap-2 rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2.5"
        >
            <Checkbox
                :checked="props.modelValue.enabled"
                :disabled="props.isSystem"
                @update:checked="update('enabled', Boolean($event))"
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

        <!-- Pages -->
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
                    v-if="!props.isSystem"
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
                v-if="props.modelValue.pages.length === 0"
                class="rounded-lg border border-dashed border-sidebar-border/70 px-4 py-6 text-center text-sm text-muted-foreground"
            >
                {{ __('No pages configured yet.') }}
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="(page, index) in props.modelValue.pages"
                    :key="`${page.key || 'page'}-${index}`"
                    class="space-y-4 rounded-xl border border-sidebar-border/70 p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <Badge
                                variant="outline"
                                class="rounded-full text-xs"
                            >
                                {{ __('Page :n', { n: String(index + 1) }) }}
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
                            v-if="!props.isSystem && !page.is_system"
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
                                :disabled="props.isSystem || page.is_system"
                                @update:model-value="
                                    updatePage(index, 'key', String($event))
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
                                :disabled="props.isSystem || page.is_system"
                                @update:model-value="
                                    updatePage(index, 'label', String($event))
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
                                :disabled="props.isSystem || page.is_system"
                                @update:model-value="
                                    updatePage(
                                        index,
                                        'route_name',
                                        String($event),
                                    )
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
                                :disabled="props.isSystem || page.is_system"
                                @update:model-value="
                                    updatePage(index, 'path', String($event))
                                "
                            />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`page-permission-${index}`">{{
                                __('Permission name')
                            }}</Label>
                            <Input
                                :id="`page-permission-${index}`"
                                :placeholder="__('demo.dashboard.viewany')"
                                :model-value="page.permission_name"
                                :disabled="props.isSystem || page.is_system"
                                @update:model-value="
                                    updatePage(
                                        index,
                                        'permission_name',
                                        String($event),
                                    )
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
                                :disabled="props.isSystem || page.is_system"
                                @update:model-value="
                                    updatePage(
                                        index,
                                        'sort_order',
                                        Number($event),
                                    )
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
                                :disabled="props.isSystem || page.is_system"
                                @update:checked="
                                    updatePage(
                                        index,
                                        'enabled',
                                        Boolean($event),
                                    )
                                "
                            />
                            <div>
                                <p class="text-sm font-medium">
                                    {{ __('Enabled') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
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
                                :disabled="props.isSystem || page.is_system"
                                @update:checked="
                                    updatePage(
                                        index,
                                        'is_navigation',
                                        Boolean($event),
                                    )
                                "
                            />
                            <div>
                                <p class="text-sm font-medium">
                                    {{ __('Navigation entry') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
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
    </div>
</template>
