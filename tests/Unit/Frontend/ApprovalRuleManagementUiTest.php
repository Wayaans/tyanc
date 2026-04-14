<?php

declare(strict_types=1);

function normalizedApprovalRuleManagementSource(string $path): string
{
    return preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path($path))) ?? '';
}

it('uses searchable approval rule filters and keeps the toggle inside the edit dialog', function (): void {
    $filterBar = normalizedApprovalRuleManagementSource('js/components/cumpu/approval-rules/ApprovalRuleFilterBar.vue');
    $table = normalizedApprovalRuleManagementSource('js/components/cumpu/approval-rules/ApprovalRuleCapabilityTable.vue');
    $dialog = normalizedApprovalRuleManagementSource('js/components/cumpu/approval-rules/ApprovalRuleManagedEditDialog.vue');

    expect(mb_substr_count($filterBar, '<ComboboxSelect'))->toBe(3)
        ->and($filterBar)
        ->toContain("__('Search rules…')")
        ->toContain("__('Search apps…')")
        ->toContain("__('Search resources…')")
        ->toContain("__('Search actions…')")
        ->toContain("const ALL_APPS = '__all_apps__';")
        ->toContain("const ALL_RESOURCES = '__all_resources__';")
        ->toContain("const ALL_ACTIONS = '__all_actions__';")
        ->toContain("{ value: ALL_APPS, label: __('All apps') }")
        ->toContain("{ value: ALL_RESOURCES, label: __('All resources') }")
        ->toContain("{ value: ALL_ACTIONS, label: __('All actions') }")
        ->toContain(':model-value="props.modelValue.app"')
        ->toContain(':model-value="props.modelValue.resource"')
        ->toContain(':model-value="props.modelValue.action"')
        ->not->toContain("{ value: '', label: __('All apps') }")
        ->not->toContain("{ value: '', label: __('All resources') }")
        ->not->toContain("{ value: '', label: __('All actions') }")
        ->not->toContain('toFilterValue(')
        ->and($table)
        ->not->toContain('role="switch"')
        ->and($dialog)
        ->toContain("{{ __('Enable rule') }}")
        ->toContain('role="switch"')
        ->toContain('toggle.url({ approvalRule: props.rule.id })');
});
