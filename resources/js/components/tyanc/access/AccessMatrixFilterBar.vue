<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
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

const ALL_OPTION = '__all__';

const { __ } = useTranslations();

function toSelectValue(value: string): string {
    return value || ALL_OPTION;
}

function update<K extends keyof Filters>(key: K, raw: string) {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: raw === ALL_OPTION ? '' : raw,
    });
}

function clearAll() {
    emit('update:modelValue', { role: '', app: '' });
}

const hasActiveFilters = computed(
    () => Boolean(props.modelValue.role) || Boolean(props.modelValue.app),
);

const activeCount = computed(
    () => (props.modelValue.role ? 1 : 0) + (props.modelValue.app ? 1 : 0),
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Select
            :model-value="toSelectValue(props.modelValue.role)"
            @update:model-value="update('role', String($event))"
        >
            <SelectTrigger class="h-8 w-40 text-xs">
                <SelectValue :placeholder="__('All roles')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="ALL_OPTION">{{
                    __('All roles')
                }}</SelectItem>
                <SelectItem
                    v-for="role in props.roles"
                    :key="role.id"
                    :value="String(role.id)"
                >
                    {{ role.name }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select
            :model-value="toSelectValue(props.modelValue.app)"
            @update:model-value="update('app', String($event))"
        >
            <SelectTrigger class="h-8 w-40 text-xs">
                <SelectValue :placeholder="__('All apps')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="ALL_OPTION">{{
                    __('All apps')
                }}</SelectItem>
                <SelectItem
                    v-for="app in props.apps"
                    :key="app.id"
                    :value="app.key"
                >
                    {{ app.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <template v-if="hasActiveFilters">
            <Badge variant="secondary" class="rounded-full text-xs">
                {{ __(':n active', { n: String(activeCount) }) }}
            </Badge>
            <Button
                variant="ghost"
                size="sm"
                class="h-7 gap-1 px-2 text-xs text-muted-foreground"
                @click="clearAll"
            >
                <X class="size-3" />
                {{ __('Clear') }}
            </Button>
        </template>
    </div>
</template>
