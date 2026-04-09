<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div
        class="space-y-3 rounded-2xl border border-sidebar-border/70 bg-background/80 p-4"
    >
        <div class="grid gap-3 sm:grid-cols-3">
            <Skeleton class="h-10 rounded-xl" />
            <Skeleton class="h-10 rounded-xl" />
            <Skeleton class="h-10 rounded-xl" />
        </div>
        <div class="space-y-2">
            <Skeleton class="h-12 rounded-xl" />
            <Skeleton class="h-12 rounded-xl" />
            <Skeleton class="h-12 rounded-xl" />
            <Skeleton class="h-12 rounded-xl" />
            <Skeleton class="h-12 rounded-xl" />
        </div>
    </div>
</template>
