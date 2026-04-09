<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/translations';

const emit = defineEmits<{
    retry: [];
}>();

const props = defineProps<{
    title?: string;
    description?: string;
}>();

const { __ } = useTranslations();

const title = computed(() => props.title ?? __('Unable to load table data'));
const description = computed(
    () => props.description ?? __('Try again or refresh the page.'),
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-destructive/30 bg-destructive/5 px-6 py-14 text-center"
    >
        <div
            class="flex size-11 items-center justify-center rounded-full border border-destructive/30 bg-background text-destructive"
        >
            <AlertTriangle class="size-5" />
        </div>
        <div class="space-y-1.5">
            <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
            <p class="max-w-md text-sm leading-6 text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <Button variant="outline" @click="emit('retry')">
            {{ __('Try again') }}
        </Button>
    </div>
</template>
