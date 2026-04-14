<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    ExternalLink,
    FileEdit,
    KeyRound,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import type {
    UserUpdateDraft,
    UserUpdateDraftState,
} from '@/types/tyanc/users';

const props = defineProps<{
    draft: UserUpdateDraft;
    commitLoading?: boolean;
}>();

const emit = defineEmits<{
    submit: [];
    commit: [];
}>();

const { __ } = useTranslations();

type StateConfig = {
    containerClass: string;
    iconComponent: unknown;
    iconClass: string;
    title: string;
    description: string;
    showSubmit: boolean;
    showCommit: boolean;
};

const stateConfigs = computed(
    (): Record<UserUpdateDraftState, StateConfig> => ({
        draft: {
            containerClass:
                'border-sky-200/60 bg-sky-50/50 dark:border-sky-500/20 dark:bg-sky-500/[0.07]',
            iconComponent: FileEdit,
            iconClass: 'text-sky-600 dark:text-sky-400',
            title: __('Draft saved'),
            description: __(
                'Your changes are saved as a draft (revision :n). Submit for approval when ready.',
                { n: String(props.draft.revision) },
            ),
            showSubmit: true,
            showCommit: false,
        },
        submitted_for_approval: {
            containerClass:
                'border-amber-200/60 bg-amber-50/50 dark:border-amber-500/20 dark:bg-amber-500/[0.07]',
            iconComponent: Clock,
            iconClass: 'text-amber-600 dark:text-amber-400',
            title: __('Awaiting approval'),
            description: __(
                'Draft revision :n is pending review. You cannot save new changes until the request is resolved.',
                { n: String(props.draft.revision) },
            ),
            showSubmit: false,
            showCommit: false,
        },
        approved_for_commit: {
            containerClass:
                'border-emerald-200/60 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/[0.07]',
            iconComponent: CheckCircle2,
            iconClass: 'text-emerald-600 dark:text-emerald-400',
            title: __('Approved — ready to commit'),
            description: __(
                'Revision :n was approved. Commit the changes to apply them.',
                { n: String(props.draft.revision) },
            ),
            showSubmit: false,
            showCommit: true,
        },
        rejected_for_revision: {
            containerClass:
                'border-red-200/60 bg-red-50/50 dark:border-red-500/20 dark:bg-red-500/[0.07]',
            iconComponent: XCircle,
            iconClass: 'text-red-500 dark:text-red-400',
            title: __('Changes rejected'),
            description: __(
                'Revision :n was rejected. Edit and save an updated draft, then re-submit for approval.',
                { n: String(props.draft.revision) },
            ),
            showSubmit: true,
            showCommit: false,
        },
        committed: {
            containerClass:
                'border-emerald-200/60 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/[0.07]',
            iconComponent: CheckCircle2,
            iconClass: 'text-emerald-600 dark:text-emerald-400',
            title: __('Changes committed'),
            description: __('Revision :n was committed successfully.', {
                n: String(props.draft.revision),
            }),
            showSubmit: false,
            showCommit: false,
        },
    }),
);

const config = computed(() => stateConfigs.value[props.draft.state]);

const staleWarning = computed(
    () =>
        props.draft.has_stale_subject_revision &&
        props.draft.state !== 'committed',
);

const changedFieldsDisplay = computed(() => {
    const fields = props.draft.changed_fields;

    if (fields.length === 0) {
        return null;
    }

    if (fields.length <= 3) {
        return fields.join(', ');
    }

    return `${fields.slice(0, 3).join(', ')} +${fields.length - 3}`;
});
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border px-4 py-3.5"
        :class="config.containerClass"
    >
        <div class="flex items-start gap-3">
            <!-- Icon -->
            <component
                :is="config.iconComponent"
                class="mt-0.5 size-4 shrink-0"
                :class="config.iconClass"
            />

            <!-- Content -->
            <div class="min-w-0 flex-1 space-y-2">
                <div class="space-y-0.5">
                    <p class="text-sm font-medium text-foreground">
                        {{ config.title }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ config.description }}
                    </p>
                </div>

                <!-- Changed fields indicator -->
                <div
                    v-if="changedFieldsDisplay"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <span class="text-xs text-muted-foreground/70">
                        {{ __('Changed:') }}
                    </span>
                    <span class="text-xs font-medium text-foreground/80">
                        {{ changedFieldsDisplay }}
                    </span>
                </div>

                <!-- Password change indicator -->
                <div
                    v-if="props.draft.has_password_change"
                    class="flex items-center gap-1.5"
                >
                    <KeyRound class="size-3 text-muted-foreground/60" />
                    <span class="text-xs text-muted-foreground">
                        {{ __('A password change is stored in this draft.') }}
                    </span>
                </div>

                <!-- Stale revision warning -->
                <div v-if="staleWarning" class="flex items-start gap-1.5">
                    <AlertCircle
                        class="mt-0.5 size-3 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <span
                        class="text-xs text-amber-800/80 dark:text-amber-300/80"
                    >
                        {{
                            __(
                                'An older approved revision is no longer current. Submit the latest draft revision for approval before committing.',
                            )
                        }}
                    </span>
                </div>

                <!-- Request link -->
                <a
                    v-if="props.draft.relevant_request?.detail_url"
                    :href="props.draft.relevant_request.detail_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-2 hover:underline"
                >
                    {{ __('View approval request') }}
                    <ExternalLink class="size-3" />
                </a>
            </div>

            <!-- Actions -->
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    v-if="config.showSubmit"
                    type="button"
                    size="sm"
                    variant="outline"
                    class="h-7 text-xs"
                    @click="emit('submit')"
                >
                    {{ __('Submit for approval') }}
                </Button>

                <Button
                    v-if="config.showCommit"
                    type="button"
                    size="sm"
                    class="h-7 text-xs"
                    :disabled="props.commitLoading"
                    @click="emit('commit')"
                >
                    <Spinner v-if="props.commitLoading" />
                    {{ __('Commit changes') }}
                </Button>
            </div>
        </div>
    </div>
</template>
