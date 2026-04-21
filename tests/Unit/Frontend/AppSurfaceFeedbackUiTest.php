<?php

declare(strict_types=1);

function normalizedAppSurfaceFeedbackSource(string $path): string
{
    return preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path($path))) ?? '';
}

it('uses shared frontend notify api for tyanc message and settings feedback', function (): void {
    $messagesPage = normalizedAppSurfaceFeedbackSource('js/pages/tyanc/messages/Index.vue');
    $settingsFooter = normalizedAppSurfaceFeedbackSource('js/components/tyanc/settings/SettingsFormFooter.vue');

    expect($messagesPage)
        ->toContain("import { notify } from '@/lib/notify';")
        ->not->toContain("import { toast } from 'vue-sonner';")
        ->toContain("notify.error(__('Unable to load the messaging workspace.'));")
        ->toContain("notify.error(__('Unable to load the selected conversation.'));")
        ->toContain("notify.success(__('Conversation deleted.'));")
        ->and($settingsFooter)
        ->toContain("import { watch } from 'vue';")
        ->toContain("import { notify } from '@/lib/notify';")
        ->toContain('watch(')
        ->toContain('() => props.recentlySuccessful,')
        ->toContain("notify.success(__('Saved.'));")
        ->not->toContain('v-show="recentlySuccessful"');
});

it('uses shared feedback primitives for representative cumpu flows', function (): void {
    $syncCard = normalizedAppSurfaceFeedbackSource('js/components/cumpu/approval-rules/ApprovalRuleSyncStatusCard.vue');
    $approvalShow = normalizedAppSurfaceFeedbackSource('js/pages/cumpu/approvals/Show.vue');

    expect($syncCard)
        ->toContain("import { notify } from '@/lib/notify';")
        ->toContain("notify.error(__('Unable to sync approval capabilities.'));")
        ->not->toContain('status?: string | null;')
        ->not->toContain('props.status')
        ->and($approvalShow)
        ->toContain("import { notify } from '@/lib/notify';")
        ->toContain("notify.success(__('Approval granted.'));")
        ->toContain("notify.success(__('Approval rejected.'));")
        ->toContain("notify.success(__('Approval request cancelled.'));")
        ->toContain("notify.error(__('Unable to update this approval request.'));");
});
