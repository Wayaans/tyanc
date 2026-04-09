<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next';
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
import { destroy, edit, show } from '@/routes/tyanc/users';

const props = defineProps<{
    userId: string;
    userName: string;
}>();

const { __ } = useTranslations();

const confirmingDelete = ref(false);

function handleOpenChange(open: boolean) {
    if (!open) {
        confirmingDelete.value = false;
    }
}

function goToShow() {
    router.visit(show.url({ user: props.userId }));
}

function goToEdit() {
    router.visit(edit.url({ user: props.userId }));
}

function handleDelete() {
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;

        return;
    }

    router.delete(destroy.url({ user: props.userId }), {
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
                {{ props.userName }}
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <DropdownMenuItem @click="goToShow">
                <Eye class="size-4" />
                {{ __('View details page') }}
            </DropdownMenuItem>

            <DropdownMenuItem @click="goToEdit">
                <Pencil class="size-4" />
                {{ __('Update this user') }}
            </DropdownMenuItem>

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
                            : __('Delete this account')
                    }}
                </span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
