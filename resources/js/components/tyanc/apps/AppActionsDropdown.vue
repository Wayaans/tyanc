<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/lib/translations';
import { destroy, edit } from '@/routes/tyanc/apps';

const props = defineProps<{
    appKey: string;
    appLabel: string;
    isSystem: boolean;
}>();

const { __ } = useTranslations();

const confirmingDelete = ref(false);

function handleOpenChange(open: boolean) {
    if (!open) {
        confirmingDelete.value = false;
    }
}

function goToEdit() {
    router.visit(edit.url({ app: props.appKey }));
}

function handleDelete() {
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;

        return;
    }

    router.delete(destroy.url({ app: props.appKey }), {
        preserveScroll: true,
        onFinish: () => {
            confirmingDelete.value = false;
        },
    });
}
</script>

<template>
    <DropdownMenu @update:open="handleOpenChange">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-8 data-[state=open]:bg-muted"
                :aria-label="__('Open actions menu')"
            >
                <MoreHorizontal class="size-4" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuLabel
                class="truncate text-xs font-normal text-muted-foreground"
            >
                {{ props.appLabel }}
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <DropdownMenuItem @click="goToEdit">
                <Pencil class="size-4" />
                {{ __('Edit this app') }}
            </DropdownMenuItem>

            <template v-if="!props.isSystem">
                <DropdownMenuSeparator />

                <DropdownMenuItem
                    :class="[
                        'gap-2 focus:bg-destructive/10',
                        confirmingDelete
                            ? 'text-destructive focus:text-destructive'
                            : 'text-destructive/80 focus:text-destructive',
                    ]"
                    @click.stop="handleDelete"
                >
                    <Trash2 class="size-4 shrink-0" />
                    <span class="truncate">
                        {{
                            confirmingDelete
                                ? __('Click again to confirm')
                                : __('Delete this app')
                        }}
                    </span>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
