<script setup lang="ts">
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        processing: boolean;
        recentlySuccessful: boolean;
        label?: string;
    }>(),
    { label: 'Save changes' },
);
</script>

<template>
    <div class="flex items-center gap-4">
        <Button type="submit" :disabled="processing">
            {{ processing ? 'Saving…' : label }}
        </Button>

        <Transition
            enter-active-class="transition ease-in-out"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in-out"
            leave-to-class="opacity-0"
        >
            <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                Saved.
            </p>
        </Transition>
    </div>
</template>
