<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, ShieldCheck, Trash2 } from 'lucide-vue-next';
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
import { destroy } from '@/routes/tyanc/roles';
import type { RoleRow } from '@/types';

const props = defineProps<{
    role: RoleRow;
}>();

const emit = defineEmits<{
    edit: [role: RoleRow];
    assignPermissions: [role: RoleRow];
}>();

const { __ } = useTranslations();

const confirmingDelete = ref(false);

function handleOpenChange(open: boolean) {
    if (!open) {
        confirmingDelete.value = false;
    }
}

function handleDelete() {
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;

        return;
    }

    router.delete(destroy.url({ role: props.role.id }), {
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
                {{ props.role.name }}
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <DropdownMenuItem @click="emit('assignPermissions', props.role)">
                <ShieldCheck class="size-4" />
                {{ __('Assign permissions') }}
            </DropdownMenuItem>

            <DropdownMenuItem @click="emit('edit', props.role)">
                <Pencil class="size-4" />
                {{ __('Edit this role') }}
            </DropdownMenuItem>

            <template v-if="!props.role.is_delete_protected">
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
                                : __('Delete this role')
                        }}
                    </span>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
