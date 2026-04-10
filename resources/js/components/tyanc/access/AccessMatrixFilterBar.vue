<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/lib/translations';
import type { AppData, RoleData } from '@/types';

type Filters = {
    role: string;
    app: string;
};

const props = defineProps<{
    modelValue: Filters;
    roles: RoleData[];
    apps: AppData[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Filters];
}>();

const { __ } = useTranslations();

// Sentinel used internally so the Select component always has a non-empty value.
const UNSET = '__unset__';

function toSelectValue(value: string): string {
    return value || UNSET;
}

function update<K extends keyof Filters>(key: K, raw: string) {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: raw === UNSET ? '' : raw,
    });
}

function clearAll() {
    emit('update:modelValue', { role: '', app: '' });
}

const hasActiveFilters = computed(
    () => Boolean(props.modelValue.role) || Boolean(props.modelValue.app),
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <!-- Role single-select -->
        <Select
            :model-value="toSelectValue(props.modelValue.role)"
            @update:model-value="update('role', String($event))"
        >
            <SelectTrigger class="h-8 w-44 text-xs">
                <SelectValue :placeholder="__('Select a role')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="UNSET" disabled>
                    {{ __('Select a role') }}
                </SelectItem>
                <SelectItem
                    v-for="role in props.roles"
                    :key="role.id"
                    :value="String(role.id)"
                >
                    {{ role.name }}
                </SelectItem>
            </SelectContent>
        </Select>

        <!-- App single-select -->
        <Select
            :model-value="toSelectValue(props.modelValue.app)"
            @update:model-value="update('app', String($event))"
        >
            <SelectTrigger class="h-8 w-44 text-xs">
                <SelectValue :placeholder="__('Select an app')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="UNSET" disabled>
                    {{ __('Select an app') }}
                </SelectItem>
                <SelectItem
                    v-for="app in props.apps"
                    :key="app.id"
                    :value="app.key"
                >
                    {{ app.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Button
            v-if="hasActiveFilters"
            variant="ghost"
            size="sm"
            class="h-8 gap-1 px-2 text-xs text-muted-foreground"
            @click="clearAll"
        >
            <X class="size-3" />
            {{ __('Clear') }}
        </Button>

        <!-- Guide text when neither selection is made -->
        <p v-if="!hasActiveFilters" class="text-xs text-muted-foreground/60">
            {{ __('Select a role and an app to edit permissions.') }}
        </p>
    </div>
</template>
