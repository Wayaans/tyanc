<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/lib/translations';
import type { SelectOption } from '@/types';

type ActionOption = SelectOption & { permission: string };

const props = defineProps<{
    /** All action options for the selected app + resource. */
    actions: ActionOption[];
    /** Currently selected permission strings (full dot-notation names). */
    modelValue: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const { __ } = useTranslations();

const allSelected = computed(
    () =>
        props.actions.length > 0 &&
        props.actions.every((a) => props.modelValue.includes(a.permission)),
);

const someSelected = computed(
    () =>
        !allSelected.value &&
        props.actions.some((a) => props.modelValue.includes(a.permission)),
);

function toggle(permission: string, checked: boolean) {
    const next = checked
        ? [...props.modelValue, permission]
        : props.modelValue.filter((p) => p !== permission);
    emit('update:modelValue', next);
}

function toggleAll(checked: boolean) {
    const permSet = new Set(props.modelValue);
    if (checked) {
        props.actions.forEach((a) => permSet.add(a.permission));
    } else {
        props.actions.forEach((a) => permSet.delete(a.permission));
    }
    emit('update:modelValue', Array.from(permSet));
}

function handleToggleAll() {
    toggleAll(!allSelected.value);
}

function handleToggle(permission: string) {
    toggle(permission, !props.modelValue.includes(permission));
}

function onKeydown(handler: () => void, event: KeyboardEvent) {
    if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault();
        handler();
    }
}
</script>

<template>
    <div
        v-if="props.actions.length > 0"
        class="divide-y divide-sidebar-border/40 rounded-lg border border-sidebar-border/70"
    >
        <!-- Select-all header row -->
        <div
            role="checkbox"
            tabindex="0"
            :aria-checked="allSelected ? true : someSelected ? 'mixed' : false"
            class="flex cursor-pointer items-center gap-2.5 bg-muted/20 px-3 py-2 transition-colors hover:bg-muted/30"
            @click="handleToggleAll"
            @keydown="onKeydown(handleToggleAll, $event)"
        >
            <Checkbox
                :model-value="
                    allSelected ? true : someSelected ? 'indeterminate' : false
                "
                tabindex="-1"
                @click.stop
                @update:model-value="(v) => toggleAll(Boolean(v))"
            />
            <span
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('All actions') }}
            </span>
            <span
                v-if="props.modelValue.length > 0"
                class="ml-auto text-xs text-muted-foreground tabular-nums"
            >
                {{
                    props.actions.filter((a) =>
                        props.modelValue.includes(a.permission),
                    ).length
                }}
                /
                {{ props.actions.length }}
            </span>
        </div>

        <!-- Individual action rows -->
        <div
            v-for="action in props.actions"
            :key="action.permission"
            role="checkbox"
            tabindex="0"
            :aria-checked="props.modelValue.includes(action.permission)"
            class="flex cursor-pointer items-center gap-2.5 px-3 py-2 transition-colors hover:bg-muted/20"
            :class="{
                'bg-primary/5': props.modelValue.includes(action.permission),
            }"
            @click="handleToggle(action.permission)"
            @keydown="onKeydown(() => handleToggle(action.permission), $event)"
        >
            <Checkbox
                :model-value="props.modelValue.includes(action.permission)"
                tabindex="-1"
                @click.stop
                @update:model-value="
                    (v) => toggle(action.permission, Boolean(v))
                "
            />
            <div class="min-w-0 flex-1">
                <span class="text-sm font-medium">{{ action.label }}</span>
                <p class="truncate font-mono text-xs text-muted-foreground">
                    {{ action.permission }}
                </p>
            </div>
        </div>
    </div>

    <p v-else class="py-3 text-center text-xs text-muted-foreground">
        {{ __('No actions available for this resource.') }}
    </p>
</template>
