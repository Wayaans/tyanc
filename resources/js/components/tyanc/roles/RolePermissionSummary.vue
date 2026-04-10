<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    permissionCount: number;
    permissions?: string[];
    maxVisible?: number;
}>();

const maxVisible = props.maxVisible ?? 3;
</script>

<template>
    <div
        v-if="props.permissions && props.permissions.length > 0"
        class="flex flex-wrap gap-1"
    >
        <Badge
            v-for="perm in props.permissions.slice(0, maxVisible)"
            :key="perm"
            variant="outline"
            class="rounded-full font-mono text-xs"
        >
            {{ perm }}
        </Badge>
        <Badge
            v-if="props.permissions.length > maxVisible"
            variant="outline"
            class="rounded-full text-xs text-muted-foreground"
        >
            +{{ props.permissions.length - maxVisible }}
        </Badge>
    </div>

    <span v-else class="text-xs text-muted-foreground/50"> &mdash; </span>
</template>
