<?php

declare(strict_types=1);

it('supports both model-value and checked checkbox contracts', function (): void {
    $component = file_get_contents(resource_path('js/components/ui/checkbox/Checkbox.vue'));

    expect($component)
        ->toContain('checked?: CheckboxRootProps["modelValue"]')
        ->toContain('"update:checked": [checked: CheckboxRootProps["modelValue"]]')
        ->toContain('modelValue: modelValue ?? checked')
        ->toContain('emit("update:modelValue", value)')
        ->toContain('emit("update:checked", value)')
        ->toContain('@update:model-value="handleUpdateModelValue"');
});
