<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';
import { Check, ChevronDown, Search } from 'lucide-vue-next';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string };

const props = withDefaults(
    defineProps<{
        options: Option[];
        placeholder?: string;
        searchPlaceholder?: string;
        modelValue?: string;
        name?: string;
        id?: string;
        class?: HTMLAttributes['class'];
        disabled?: boolean;
    }>(),
    {
        placeholder: 'Select…',
        searchPlaceholder: 'Search…',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
const searchTerm = ref('');

const selectedLabel = computed(
    () => props.options.find((o) => o.value === props.modelValue)?.label ?? '',
);

const filteredOptions = computed(() => {
    const q = searchTerm.value.toLowerCase().trim();
    if (!q) {
        return props.options;
    }
    return props.options.filter((o) => o.label.toLowerCase().includes(q));
});

function onValueChange(val: string | null) {
    if (val !== null) {
        emit('update:modelValue', val);
        open.value = false;
        searchTerm.value = '';
    }
}
</script>

<template>
    <ComboboxRoot
        v-model:open="open"
        :model-value="modelValue ?? ''"
        :disabled="disabled"
        @update:model-value="onValueChange"
    >
        <ComboboxAnchor
            :id="id"
            role="combobox"
            :aria-expanded="open"
            :tabindex="disabled ? -1 : 0"
            :class="
                cn(
                    'border-input dark:bg-input/30 dark:hover:bg-input/50 flex h-9 w-full cursor-pointer items-center justify-between gap-2 rounded-md border bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px] disabled:pointer-events-none disabled:opacity-50',
                    props.class,
                )
            "
            @click="() => {
                if (!disabled) {
                    open = true;
                }
            }"
            @keydown.enter.prevent="() => {
                if (!disabled) {
                    open = true;
                }
            }"
            @keydown.space.prevent="() => {
                if (!disabled) {
                    open = true;
                }
            }"
        >
            <span
                v-if="!selectedLabel"
                class="pointer-events-none truncate text-muted-foreground"
            >
                {{ placeholder }}
            </span>
            <span v-else class="truncate">{{ selectedLabel }}</span>

            <ComboboxTrigger class="ml-auto shrink-0 opacity-50 hover:opacity-100">
                <ChevronDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxContent
            position="popper"
            :side-offset="4"
            class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 z-50 w-(--reka-combobox-trigger-width) min-w-48 overflow-hidden rounded-md border shadow-md"
        >
            <!-- Search input -->
            <div class="flex items-center border-b px-3">
                <Search class="mr-2 size-4 shrink-0 opacity-50" />
                <ComboboxInput
                    v-model="searchTerm"
                    :placeholder="searchPlaceholder"
                    auto-focus
                    class="flex h-9 w-full bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"
                />
            </div>

            <ComboboxViewport class="max-h-60 overflow-y-auto p-1">
                <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
                    No results found.
                </ComboboxEmpty>

                <ComboboxGroup>
                    <ComboboxItem
                        v-for="option in filteredOptions"
                        :key="option.value"
                        :value="option.value"
                        class="focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                    >
                        <span class="absolute right-2 flex size-3.5 items-center justify-center">
                            <ComboboxItemIndicator>
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </span>
                        {{ option.label }}
                    </ComboboxItem>
                </ComboboxGroup>
            </ComboboxViewport>
        </ComboboxContent>

        <!-- Hidden input for native form submission -->
        <input v-if="name" type="hidden" :name="name" :value="modelValue ?? ''" />
    </ComboboxRoot>
</template>
