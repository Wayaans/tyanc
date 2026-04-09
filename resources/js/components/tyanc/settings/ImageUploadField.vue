<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    name: string;
    label: string;
    removeName: string;
    currentUrl?: string | null;
    currentUuid?: string | null;
    error?: string;
    hint?: string;
    accept?: string;
}>();

const { __ } = useTranslations();
const fileRef = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const pendingRemove = ref(false);

const displayUrl = computed<string | null>(() => {
    if (pendingRemove.value) {
        return null;
    }
    return preview.value ?? props.currentUrl ?? null;
});

function openPicker() {
    fileRef.value?.click();
}

function revokePreview(): void {
    if (preview.value !== null) {
        URL.revokeObjectURL(preview.value);
    }
}

function handleChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        revokePreview();
        preview.value = URL.createObjectURL(file);
        pendingRemove.value = false;
    }
}

function remove() {
    revokePreview();
    preview.value = null;
    pendingRemove.value = true;
    if (fileRef.value) {
        fileRef.value.value = '';
    }
}

onUnmounted(() => {
    revokePreview();
});
</script>

<template>
    <div class="space-y-2">
        <p
            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
        >
            {{ label }}
        </p>

        <div
            class="relative flex h-20 w-full items-center justify-center overflow-hidden rounded-md border bg-muted/30"
        >
            <img
                v-if="displayUrl"
                :src="displayUrl"
                :alt="label"
                class="max-h-16 max-w-full object-contain"
            />
            <span v-else class="text-xs text-muted-foreground">
                {{ __('No image set') }}
            </span>

            <button
                v-if="displayUrl"
                type="button"
                class="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-background/80 shadow-sm transition hover:bg-background"
                :aria-label="`Remove ${label}`"
                @click="remove"
            >
                <X class="size-3" />
            </button>
        </div>

        <div class="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="openPicker"
            >
                {{ displayUrl ? __('Replace') : __('Upload') }}
            </Button>
            <span class="text-xs text-muted-foreground">
                {{ hint ?? 'PNG, JPG, WebP · Max 2 MB' }}
            </span>
        </div>

        <!-- Hidden file input -->
        <input
            ref="fileRef"
            type="file"
            :name="name"
            :accept="accept ?? 'image/*'"
            class="hidden"
            @change="handleChange"
        />

        <!-- Remove flag: only sent when user explicitly removed an existing asset -->
        <input
            v-if="pendingRemove && currentUuid"
            type="hidden"
            :name="removeName"
            value="1"
        />

        <InputError :message="error" />
    </div>
</template>
