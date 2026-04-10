<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { store, update } from '@/routes/tyanc/roles';
import type { RoleRow } from '@/types';

type RoleFormFields = {
    name: string;
    level: number;
};

const props = defineProps<{
    open: boolean;
    editingRole?: RoleRow | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { __ } = useTranslations();

const defaultForm = (): RoleFormFields => ({
    name: '',
    level: 10,
});

const form = ref<RoleFormFields>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

const isEditing = computed(() => Boolean(props.editingRole));
const title = computed(() =>
    isEditing.value ? __('Edit role') : __('New role'),
);

watch(
    () => props.editingRole,
    (role) => {
        if (role) {
            form.value = {
                name: role.name,
                level: role.level,
            };
        } else {
            form.value = defaultForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function submit() {
    processing.value = true;
    errors.value = {};

    const isEdit = isEditing.value && props.editingRole;
    const url = isEdit
        ? update.url({ role: props.editingRole!.id })
        : store.url();
    const method = isEdit ? 'patch' : 'post';

    router[method](url, form.value, {
        preserveScroll: true,
        onSuccess: () => close(),
        onError: (responseErrors) => {
            errors.value = responseErrors as Partial<Record<string, string>>;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? __('Update the role name and hierarchy level.')
                            : __(
                                  'Create a new role. Assign permissions separately after creation.',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="role-name">{{ __('Role name') }}</Label>
                        <Input
                            id="role-name"
                            type="text"
                            placeholder="editor"
                            :model-value="form.name"
                            @update:model-value="form.name = String($event)"
                        />
                        <FormFieldSupport :error="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role-level">{{ __('Level') }}</Label>
                        <Input
                            id="role-level"
                            type="number"
                            min="1"
                            max="999"
                            :model-value="String(form.level)"
                            @update:model-value="form.level = Number($event)"
                        />
                        <FormFieldSupport
                            :hint="
                                __(
                                    'Higher levels represent higher hierarchy and wider access.',
                                )
                            "
                            :error="errors.level"
                        />
                    </div>
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="close"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button :disabled="processing" @click="submit">
                    <Spinner v-if="processing" />
                    {{ isEditing ? __('Save changes') : __('Create role') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
